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

use Piwik\Piwik;
use Piwik\Common;
use Piwik\Db;

/**
 * Rebel Audit Log collects changes in your Matomo installation, like
 * new users, user permissions changed, a website was added or changed etc.
 * The Rebel Audit Log is accessible by super users only, and could be exported
 * in various formats.
 */
class API extends \Piwik\Plugin\API
{
    public function getAudits(
        $offset = 0,
        $limit = 50000,
        $excludeConsole = false,
        $selectedUser = null,
        $selectedEventBase = null,
        $order = 'ASC'
    ) {
        Piwik::checkUserHasSuperUserAccess();

        $offset = (int) $offset;
        $limit = (int) $limit;

        $baseTable = Common::prefixTable('rebel_audit');
        $detailsTable = Common::prefixTable('rebel_audit_details');

        // Build the WHERE clause dynamically based on filters
        $condition = "WHERE 1=1";
        $params = [];

        if ($excludeConsole) {
            $condition .= " AND user != ?";
            $params[] = 'Console';
        }

        if ($selectedUser) {
            $condition .= " AND user = ?";
            $params[] = $selectedUser;
        }

        if ($selectedEventBase) {
            $condition .= " AND event_base = ?";
            $params[] = $selectedEventBase;
        }

        $distinctKeysQuery = "SELECT DISTINCT `key` FROM $detailsTable";
        $keys = Db::fetchAll($distinctKeysQuery);
        $dynamicColumns = [];

        if (!empty($keys)) {
            foreach ($keys as $keyRow) {
                $keyName = $keyRow['key'];

                // Sanitize column name and add to SELECT clause dynamically
                $safeColumnName = '`' . str_replace(['`'], '', $keyName) . '`';
                $dynamicColumns[] = "MAX(CASE WHEN d.`key` = ? THEN d.`value` ELSE NULL END) AS $safeColumnName";
                $params[] = $keyName;
            }
        }
        $dynamicColumnsSql = !empty($dynamicColumns) ? ', ' . implode(", ", $dynamicColumns) : '';
        $sql = "SELECT
                    a.id,
                    a.event_base,
                    a.event_task,
                    a.user,
                    a.ip,
                    a.session,
                    a.audit_log,
                    a.timestamp
                    $dynamicColumnsSql
                FROM $baseTable a
                LEFT JOIN $detailsTable d ON a.id = d.base_id
                $condition
                GROUP BY a.id
                ORDER BY a.timestamp $order
                LIMIT $offset, $limit";

        return Db::fetchAll($sql, $params);
    }

    public function logAudit(
        string $eventBase,
        string $eventTask,
        string $user,
        string $ip,
        ?string $session,
        string $auditLog,
        ?array $details
    ): void {
        $query = "INSERT INTO `" . Common::prefixTable('rebel_audit') . "`
            (event_base, event_task, user, ip, session, audit_log) VALUES (?,?,?,?,?,?)";
        $params = [$eventBase, $eventTask, $user, $ip, $session, $auditLog];

        $db = $this->getDb();
        $db->query($query, $params);
        $lastId = (int) $db->lastInsertId();

        if (is_array($details)) {
            $utility = new Utility();
            $cleanArray = $utility->removeEmpty($details);

            foreach ($cleanArray as $key => $value) {
                $query = "INSERT INTO `" . Common::prefixTable('rebel_audit_details') . "`
                (base_id, `key`, `value`) VALUES (?,?,?)";
                $params = [$lastId, $key, $value];

                $db->query($query, $params);
            }
        }
    }
    private function getDb()
    {
        return Db::get();
    }
}
