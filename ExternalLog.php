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

use Exception;
use PDO;
use Piwik\Config;
use Piwik\Url;

/**
 * A copy of the audit log in a database outside Matomo, MariaDB/MySQL or PostgreSQL, so that
 * the log does not depend on Matomo's own database. It is configured in the [RebelAuditLog]
 * section of config.ini.php:
 *
 *     external_dsn = "pgsql:host=audit.example.org;port=5432;dbname=audit"
 *     external_username = "matomo_audit"
 *     external_password = "..."
 *     external_table_prefix = ""
 *     external_source = "analytics.example.org"
 */
class ExternalLog
{
    private const TIMEOUT_SECONDS = 3;

    private static ?PDO $connection = null;

    private array $config;

    /**
     * @param array|null $config by default the [RebelAuditLog] section of the config file
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) Config::getInstance()->RebelAuditLog;
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['external_dsn']);
    }

    /**
     * Forgets the open connection, so that the next use connects again.
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /**
     * @param array<string, string> $details
     * @throws Exception if the entry could not be written
     */
    public function add(string $eventBase, string $eventTask, string $user, string $ip, ?string $session, string $log, array $details): void
    {
        $connection = $this->getConnection();
        $columns = ['source', 'event_base', 'event_task', 'user', 'ip', 'session', 'audit_log'];
        $sql = 'INSERT INTO ' . $this->quote($this->getTable(AuditLog::TABLE))
            . ' (' . implode(', ', array_map([$this, 'quote'], $columns)) . ') VALUES (?,?,?,?,?,?,?)';

        $connection->beginTransaction();
        try {
            $values = [$this->getSource(), $eventBase, $eventTask, $user, $ip, $session, $log];
            if ($this->getDriver() === 'pgsql') {
                $statement = $connection->prepare($sql . ' RETURNING ' . $this->quote('id'));
                $statement->execute($values);
                $id = (int) $statement->fetchColumn();
            } else {
                $connection->prepare($sql)->execute($values);
                $id = (int) $connection->lastInsertId();
            }

            $insertDetail = $connection->prepare(
                'INSERT INTO ' . $this->quote($this->getTable(AuditLog::DETAILS_TABLE))
                . ' (' . $this->quote('base_id') . ', ' . $this->quote('key') . ', ' . $this->quote('value') . ') VALUES (?,?,?)'
            );
            foreach ($details as $key => $value) {
                $insertDetail->execute([$id, (string) $key, $value]);
            }

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Creates the tables of the log in the external database, unless they exist.
     */
    public function createTables(): void
    {
        foreach ($this->getTableDefinitions() as $sql) {
            $this->getConnection()->exec($sql);
        }
    }

    /**
     * @return int how many entries the external database has
     */
    public function count(): int
    {
        return (int) $this->getConnection()->query('SELECT COUNT(*) FROM ' . $this->quote($this->getTable(AuditLog::TABLE)))->fetchColumn();
    }

    /**
     * @return string[] the statements that create the tables for the configured kind of database
     */
    public function getTableDefinitions(): array
    {
        $entries = $this->getTable(AuditLog::TABLE);
        $details = $this->getTable(AuditLog::DETAILS_TABLE);

        if ($this->getDriver() === 'pgsql') {
            return [
                "CREATE TABLE IF NOT EXISTS \"$entries\" (
                    \"id\" BIGSERIAL PRIMARY KEY,
                    \"source\" VARCHAR(255) NOT NULL DEFAULT '',
                    \"event_base\" VARCHAR(255) NOT NULL,
                    \"event_task\" VARCHAR(255) NOT NULL,
                    \"user\" VARCHAR(100) NOT NULL,
                    \"ip\" TEXT,
                    \"session\" VARCHAR(191),
                    \"audit_log\" VARCHAR(1024) NOT NULL,
                    \"timestamp\" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE INDEX IF NOT EXISTS \"{$entries}_timestamp\" ON \"$entries\" (\"timestamp\")",
                "CREATE INDEX IF NOT EXISTS \"{$entries}_user\" ON \"$entries\" (\"user\")",
                "CREATE TABLE IF NOT EXISTS \"$details\" (
                    \"id\" BIGSERIAL PRIMARY KEY,
                    \"base_id\" BIGINT NOT NULL,
                    \"key\" VARCHAR(512) NOT NULL,
                    \"value\" TEXT NOT NULL
                )",
                "CREATE INDEX IF NOT EXISTS \"{$details}_base_id\" ON \"$details\" (\"base_id\")",
            ];
        }

        return [
            "CREATE TABLE IF NOT EXISTS `$entries` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `source` VARCHAR(255) NOT NULL DEFAULT '',
                `event_base` VARCHAR(255) NOT NULL,
                `event_task` VARCHAR(255) NOT NULL,
                `user` VARCHAR(100) NOT NULL,
                `ip` TEXT,
                `session` VARCHAR(191) NULL,
                `audit_log` VARCHAR(1024) NOT NULL,
                `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_timestamp` (`timestamp`),
                KEY `idx_user` (`user`)
            ) DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `$details` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `base_id` BIGINT UNSIGNED NOT NULL,
                `key` VARCHAR(512) NOT NULL,
                `value` TEXT NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_base_id` (`base_id`)
            ) DEFAULT CHARSET=utf8mb4",
        ];
    }

    /**
     * @return string "mysql", also for MariaDB, or "pgsql"
     * @throws Exception if the configured database is of another kind
     */
    public function getDriver(): string
    {
        $driver = strtolower((string) strstr((string) ($this->config['external_dsn'] ?? ''), ':', true));
        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new Exception('RebelAuditLog: external_dsn has to start with "mysql:" or "pgsql:".');
        }

        return $driver;
    }

    private function getConnection(): PDO
    {
        if (self::$connection === null) {
            $this->getDriver();

            self::$connection = new PDO(
                (string) $this->config['external_dsn'],
                (string) ($this->config['external_username'] ?? ''),
                (string) ($this->config['external_password'] ?? ''),
                // A database that does not answer must not hold up Matomo for long.
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => self::TIMEOUT_SECONDS]
            );
        }

        return self::$connection;
    }

    private function getTable(string $name): string
    {
        $prefix = (string) ($this->config['external_table_prefix'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
            throw new Exception('RebelAuditLog: external_table_prefix can only have letters, digits and underscores.');
        }

        return $prefix . $name;
    }

    /**
     * @return string what tells the entries of this Matomo from those of others in the same database
     */
    private function getSource(): string
    {
        return mb_substr((string) ($this->config['external_source'] ?? '') ?: (string) Url::getHost(false), 0, 255);
    }

    private function quote(string $identifier): string
    {
        return $this->getDriver() === 'pgsql' ? "\"$identifier\"" : "`$identifier`";
    }
}
