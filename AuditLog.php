<?php

/**
 * The Rebel Audit Log plugin for Matomo.
 *
 * Copyright (C) 2024 Digitalist Open Cloud <cloud@digitalist.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Piwik\Plugins\RebelAuditLog;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Log\LoggerInterface;

/**
 * The stored audit log: one entry per logged action, with its details as keys and values.
 */
class AuditLog
{
    public const TABLE = 'rebel_audit';
    public const DETAILS_TABLE = 'rebel_audit_details';

    // The user that actions from the command line are logged as.
    public const CONSOLE_USER = 'Console';

    private const COLUMNS = ['id', 'event_base', 'event_task', 'user', 'ip', 'session', 'audit_log', 'timestamp'];
    private const IDS_PER_QUERY = 1000;

    public function install(): void
    {
        DbHelper::createTable(self::TABLE, "
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event_base` VARCHAR(255) NOT NULL,
            `event_task` VARCHAR(255) NOT NULL,
            `user` VARCHAR(100) NOT NULL,
            `ip` TEXT,
            `session` VARCHAR(191) NULL,
            `audit_log` VARCHAR(1024) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user`),
            KEY `idx_event_base` (`event_base`),
            KEY `idx_user_eventbase_timestamp` (`user`, `event_base`, `timestamp`),
            KEY `idx_timestamp` (`timestamp`)");

        DbHelper::createTable(self::DETAILS_TABLE, "
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `base_id` INT UNSIGNED NOT NULL,
            `key` VARCHAR(512) NOT NULL,
            `value` TEXT NOT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_base_id` (`base_id`),
            KEY `idx_key` (`key`(191))");
    }

    public function uninstall(): void
    {
        Db::dropTables([Common::prefixTable(self::TABLE), Common::prefixTable(self::DETAILS_TABLE)]);
    }

    /**
     * @param array<string, string> $details
     * @return int the ID of the entry
     */
    public function add(string $eventBase, string $eventTask, string $user, string $ip, ?string $session, string $log, array $details): int
    {
        // Cut to what the columns hold: an entry must not be lost because, say, a login that was
        // tried is too long.
        $eventBase = mb_substr($eventBase, 0, 255);
        $eventTask = mb_substr($eventTask, 0, 255);
        $user = mb_substr($user, 0, 100);
        $log = mb_substr($log, 0, 1024);
        $shortDetails = [];
        foreach ($details as $key => $value) {
            $shortDetails[mb_substr((string) $key, 0, 512)] = $value;
        }

        $db = Db::get();
        $db->query(
            'INSERT INTO `' . Common::prefixTable(self::TABLE) . '` (event_base, event_task, user, ip, session, audit_log) VALUES (?,?,?,?,?,?)',
            [$eventBase, $eventTask, $user, $ip, $session, $log]
        );
        $id = (int) $db->lastInsertId();

        foreach ($shortDetails as $key => $value) {
            $db->query(
                'INSERT INTO `' . Common::prefixTable(self::DETAILS_TABLE) . '` (base_id, `key`, `value`) VALUES (?,?,?)',
                [$id, $key, $value]
            );
        }

        $external = new ExternalLog();
        if ($external->isConfigured()) {
            try {
                $external->add($eventBase, $eventTask, $user, $ip, $session, $log, $shortDetails);
            } catch (\Throwable $e) {
                // The entry is in Matomo's database. What was done goes on.
                ExternalLog::disconnect();
                StaticContainer::get(LoggerInterface::class)->error(
                    'RebelAuditLog: an entry could not be written to the external database: {message}',
                    ['message' => $e->getMessage()]
                );
            }
        }

        return $id;
    }

    /**
     * @param array{excludeConsole?: bool, user?: string, eventBase?: string, startDate?: string, endDate?: string} $filters
     *        the dates are days, as YYYY-MM-DD, and both are part of the range
     * @return array[] the entries, without their details
     */
    public function getEntries(array $filters, int $offset, int $limit, bool $newestFirst): array
    {
        [$where, $bind] = $this->getCondition($filters);

        return Db::fetchAll(
            'SELECT `' . implode('`, `', self::COLUMNS) . '` FROM `' . Common::prefixTable(self::TABLE) . "` $where"
            . ' ORDER BY `timestamp` ' . ($newestFirst ? 'DESC, `id` DESC' : 'ASC, `id` ASC')
            . ' LIMIT ' . max(0, $offset) . ', ' . max(0, $limit),
            $bind
        );
    }

    /**
     * Adds the details of each entry to it as columns. Every entry gets every key that any of
     * them has, so that the entries can be exported as a table.
     *
     * @param array[] $entries
     * @return array[]
     */
    public function addDetails(array $entries): array
    {
        $details = [];
        $keys = [];
        foreach (array_chunk(array_column($entries, 'id'), self::IDS_PER_QUERY) as $ids) {
            $rows = Db::fetchAll(
                'SELECT base_id, `key`, `value` FROM `' . Common::prefixTable(self::DETAILS_TABLE) . '`'
                . ' WHERE base_id IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY id'
            );
            foreach ($rows as $row) {
                // A detail named like a column of the entry itself must not replace it.
                $key = in_array($row['key'], self::COLUMNS, true) ? 'detail_' . $row['key'] : $row['key'];
                $details[$row['base_id']][$key] = $row['value'];
                $keys[$key] = '';
            }
        }

        foreach ($entries as &$entry) {
            $entry += ($details[$entry['id']] ?? []) + $keys;
        }

        return $entries;
    }

    /**
     * Deletes the entries from before the day, with their details.
     *
     * @return int how many entries were deleted
     */
    public function deleteOlderThan(Date $day): int
    {
        $table = Common::prefixTable(self::TABLE);
        $before = $day->toString() . ' 00:00:00';

        Db::query(
            'DELETE d FROM `' . Common::prefixTable(self::DETAILS_TABLE) . "` d INNER JOIN `$table` a ON a.id = d.base_id WHERE a.`timestamp` < ?",
            [$before]
        );

        return (int) Db::query("DELETE FROM `$table` WHERE `timestamp` < ?", [$before])->rowCount();
    }

    public function count(array $filters): int
    {
        [$where, $bind] = $this->getCondition($filters);

        return (int) Db::fetchOne('SELECT COUNT(*) FROM `' . Common::prefixTable(self::TABLE) . "` $where", $bind);
    }

    /**
     * @return string[] the users that have entries
     */
    public function getUsers(): array
    {
        return array_column(Db::fetchAll('SELECT DISTINCT `user` FROM `' . Common::prefixTable(self::TABLE) . '` ORDER BY `user`'), 'user');
    }

    /**
     * @return string[] the event bases that have entries, which are mostly plugin names
     */
    public function getEventBases(): array
    {
        return array_column(Db::fetchAll('SELECT DISTINCT `event_base` FROM `' . Common::prefixTable(self::TABLE) . '` ORDER BY `event_base`'), 'event_base');
    }

    /**
     * @return array{0: string, 1: array} a WHERE clause, or nothing, and its bound values
     */
    private function getCondition(array $filters): array
    {
        $conditions = [];
        $bind = [];

        if (!empty($filters['excludeConsole'])) {
            $conditions[] = '`user` != ?';
            $bind[] = self::CONSOLE_USER;
        }
        if (!empty($filters['user'])) {
            $conditions[] = '`user` = ?';
            $bind[] = $filters['user'];
        }
        if (!empty($filters['eventBase'])) {
            $conditions[] = '`event_base` = ?';
            $bind[] = $filters['eventBase'];
        }
        if (!empty($filters['startDate'])) {
            $conditions[] = '`timestamp` >= ?';
            $bind[] = Date::factory($filters['startDate'])->toString() . ' 00:00:00';
        }
        if (!empty($filters['endDate'])) {
            $conditions[] = '`timestamp` <= ?';
            $bind[] = Date::factory($filters['endDate'])->toString() . ' 23:59:59';
        }

        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $bind];
    }
}
