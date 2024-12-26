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
        $order = 'ASC',
        $idSite = 1
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

        // Step 1: Fetch distinct keys from matomo_rebel_audit_details
        $distinctKeysQuery = "SELECT DISTINCT `key` FROM $detailsTable";
        $keys = Db::fetchAll($distinctKeysQuery);
        $dynamicColumns = [];

        // Step 2: Handle dynamic columns only if keys exist
        if (!empty($keys)) {
            foreach ($keys as $keyRow) {
                $keyName = $keyRow['key'];

                // Sanitize column name and add to SELECT clause dynamically
                $safeColumnName = '`' . str_replace(['`'], '', $keyName) . '`';
                $dynamicColumns[] = "MAX(CASE WHEN d.`key` = ? THEN d.`value` ELSE NULL END) AS $safeColumnName";
                $params[] = $keyName;
            }
        }

        // Step 3: Generate the dynamic SELECT query
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

        // Step 4: Execute the query
        return Db::fetchAll($sql, $params);
    }
}
