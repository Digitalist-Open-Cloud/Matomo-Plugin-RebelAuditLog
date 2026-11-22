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

use Piwik\Period\Factory;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\Request;

class Controller extends ControllerAdmin
{
    private const ENTRIES_PER_PAGE = 50;

    /**
     * The audit log as a page: the entries of a period, newest first, with filters.
     */
    public function index(): string
    {
        Piwik::checkUserHasSuperUserAccess();

        $request = Request::fromRequest();
        $period = $request->getStringParameter('period', 'day');
        $date = $request->getStringParameter('date', 'today');
        $filters = [
            'excludeConsole' => $request->getBoolParameter('excludeConsole', false),
            'user' => $request->getStringParameter('user', ''),
            'eventBase' => $request->getStringParameter('event_base', ''),
        ];

        $periodOfLog = Factory::build($period, $date);
        $dates = [
            'startDate' => $periodOfLog->getDateStart()->toString(),
            'endDate' => $periodOfLog->getDateEnd()->toString(),
        ];

        $auditLog = new AuditLog();
        $total = $auditLog->count($filters + $dates);
        $totalPages = max(1, (int) ceil($total / self::ENTRIES_PER_PAGE));
        $page = min($totalPages, max(1, $request->getIntegerParameter('page', 1)));

        return $this->renderTemplate('index', [
            'audits' => $auditLog->getEntries($filters + $dates, ($page - 1) * self::ENTRIES_PER_PAGE, self::ENTRIES_PER_PAGE, true),
            'total' => $total,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'users' => $auditLog->getUsers(),
            'eventBases' => $auditLog->getEventBases(),
            'filters' => $filters,
            'dates' => $dates,
            'consoleUser' => AuditLog::CONSOLE_USER,
            // What the links to other pages and the export have to keep.
            'query' => [
                'period' => $period,
                'date' => $date,
                'user' => $filters['user'],
                'event_base' => $filters['eventBase'],
                'excludeConsole' => (int) $filters['excludeConsole'],
            ],
        ]);
    }
}
