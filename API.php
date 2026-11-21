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

/**
 * Rebel Audit Log collects changes in your Matomo installation, like
 * new users, user permissions changed, a website was added or changed etc.
 * The Rebel Audit Log is accessible by super users only, and could be exported
 * in various formats.
 *
 * @method static \Piwik\Plugins\RebelAuditLog\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    private AuditLog $auditLog;

    public function __construct(AuditLog $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /**
     * Returns entries of the audit log, each with its details as further columns.
     *
     * @param bool $excludeConsole leave out what was done from the command line
     * @param string $selectedUser only the entries of this user
     * @param string $selectedEventBase only the entries of this event base, eg, "UsersManager"
     * @param string $order "ASC" for the oldest entry first or "DESC" for the newest
     * @param string $startDate only entries from this day on, as YYYY-MM-DD
     * @param string $endDate only entries up to and including this day, as YYYY-MM-DD
     * @return array[]
     */
    public function getAudits(
        int $offset = 0,
        int $limit = 50000,
        bool $excludeConsole = false,
        string $selectedUser = '',
        string $selectedEventBase = '',
        string $order = 'ASC',
        string $startDate = '',
        string $endDate = ''
    ): array {
        Piwik::checkUserHasSuperUserAccess();

        $entries = $this->auditLog->getEntries([
            'excludeConsole' => $excludeConsole,
            'user' => $selectedUser,
            'eventBase' => $selectedEventBase,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ], $offset, $limit, strtoupper($order) === 'DESC');

        return $this->auditLog->addDetails($entries);
    }
}
