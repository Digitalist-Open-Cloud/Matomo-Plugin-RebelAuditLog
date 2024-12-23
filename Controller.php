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
use Piwik\Db;
use Piwik\Piwik;
use Piwik\View;
use Piwik\Container\StaticContainer;
use Piwik\Request;
use Piwik\Period\Factory;

//use Piwik\Plugins\RebelAuditLog\Utility;

class Controller extends \Piwik\Plugin\Controller
{
    private function getAudits(
        $offset = 0,
        $limit = 50,
        $excludeConsole = false,
        $selectedUser = null,
        $selectedEventBase = null,
        $sortDirection = 'DESC',
        $startDate = null,
        $endDate = null
    ) {
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
        if ($startDate && $endDate) {
            $condition .= " AND timestamp BETWEEN ? AND ?";
            // Log the start and end date for debugging
            // $util = new Utility();
            //$util->logger()->warn("Start Date: " . $startDate);
            //$util->logger()->warn("End Date: " . $endDate);

            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
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
                ORDER BY timestamp $sortDirection
                LIMIT $offset, $limit";

        return Db::fetchAll($sql, $params);
    }

    private function totalAudits(
        $excludeConsole = false,
        $selectedUser = null,
        $selectedEventBase = null,
        $startDate = null,
        $endDate = null
    ) {
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
        if ($startDate && $endDate) {
            $condition .= " AND timestamp BETWEEN ? AND ?";
            // Log the start and end date for debugging
            //$util = new Utility();
            //$util->logger()->warn("Start Date: " . $startDate);
            //$util->logger()->warn("End Date: " . $endDate);
            $params[] = $startDate . ' 00:00:00';
            $params[] = $endDate . ' 23:59:59';
        }

        $sql = "SELECT COUNT(*) as total FROM " . Common::prefixTable('rebel_audit') . " $condition";
        $total = (int) Db::fetchOne($sql, $params);

        return $total;
    }

    private function getUsers()
    {
        $sql = "SELECT DISTINCT user FROM " . Common::prefixTable('rebel_audit');
        return Db::fetchAll($sql);
    }

    private function getEventBases()
    {
        $sql = "SELECT DISTINCT event_base FROM " . Common::prefixTable('rebel_audit');
        return Db::fetchAll($sql);
    }

    public function index()
    {
        Piwik::checkUserHasSuperUserAccess();

        $page = Request::fromRequest()->getIntegerParameter('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $excludeConsole = Request::fromRequest()->getBoolParameter('excludeConsole', false);
        $selectedEventBase = Request::fromRequest()->getStringParameter('event_base', '');
        $selectedUser = Request::fromRequest()->getStringParameter('user', '');
        $period = Request::fromRequest()->getStringParameter('period', 'day');
        $date = Request::fromRequest()->getStringParameter('date', 'today');
        list($startDate, $endDate) = $this->calculateDateRange($period, $date);

        $audits = $this->getAudits(
            $offset,
            $limit,
            $excludeConsole,
            $selectedUser,
            $selectedEventBase,
            'DESC',
            $startDate,
            $endDate
        );
        $totalAudits = $this->totalAudits($excludeConsole, $selectedUser, $selectedEventBase, $startDate, $endDate);
        $totalPages = ceil($totalAudits / $limit);
        $users = $this->getUsers();
        $eventBases = $this->getEventBases();

        $view = new View('@RebelAudit/index');
        $this->setBasicVariablesView($view);

        return $this->renderTemplate('index', [
            'audits' => $audits,
            'total' => $totalAudits,
            'currentPage' => $page,
            'users' => $users,
            'eventBases' => $eventBases,
            'totalPages' => $totalPages,
            'excludeConsole' => $excludeConsole,
            'selectedEventBase' => $selectedEventBase,
            'selectedUser' => $selectedUser,
            'currentDate' => $date,
            'currentPeriod' => $period
        ]);
    }

    private function calculateDateRange($period, $date)
    {
        try {
            $periodObj = Factory::build($period, $date);
            $startDate = $periodObj->getDateStart()->toString('Y-m-d');
            $endDate = $periodObj->getDateEnd()->toString('Y-m-d');

            return [$startDate, $endDate];
        } catch (\Exception $e) {
            StaticContainer::get('Psr\Log\LoggerInterface')->error(
                'Error calculating date range: {message}',
                ['message' => $e->getMessage()]
            );
            return [null, null];
        }
    }
}
