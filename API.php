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
    public function getAudits($offset = 0, $limit = 50000, $excludeConsole = false, $selectedUser = null, $selectedEventBase = null, $order = 'ASC')
    {
        Piwik::checkUserHasSuperUserAccess();
        $offset = (int) $offset;
        $limit = (int) $limit;

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
        $sql = "SELECT
              id,
              event_base,
              event_task,
              user,
              ip,
              audit_log,
              timestamp
              FROM " . Common::prefixTable('rebel_audit') . "
              $condition
              ORDER BY timestamp $order
              LIMIT $offset, $limit

      ";

        return Db::fetchAll($sql, $params);
    }
}
