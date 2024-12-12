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
use Piwik\Log\LoggerInterface;
use Piwik\Container\StaticContainer;
use Piwik\Request;
use Piwik\Plugins\RebelAuditLog\API;

class Controller extends \Piwik\Plugin\Controller
{
    private function getDb()
    {
        return Db::get();
    }

    private function getAudits($offset = 0, $limit = 50, $excludeConsole = false, $selectedUser = null, $selectedEventBase = null)
    {
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
                ORDER BY timestamp DESC
                LIMIT $offset, $limit

        ";

        return Db::fetchAll($sql, $params);
    }

    private function totalAudits($excludeConsole = false, $selectedUser = null, $selectedEventBase = null)
    {
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

        $sql = "SELECT COUNT(*) as total FROM " . Common::prefixTable('rebel_audit') . " $condition";

        $total = (int) Db::fetchOne($sql, $params);
        //$this->logger()->warning($total);


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

        $page = Common::getRequestVar('page', 1, 'int');
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $excludeConsole = Request::fromRequest()->getBoolParameter('excludeConsole', false);
        $selectedEventBase = Request::fromRequest()->getStringParameter('event_base', '');
        $selectedUser = Request::fromRequest()->getStringParameter('user', '');
        //$audits = $this->getAudits($offset, $limit, $excludeConsole, $selectedUser, $selectedEventBase , 'DESC');

        $api = new API();
        $audits = $api->getAudits($offset, $limit, $excludeConsole, $selectedUser, $selectedEventBase, 'DESC');
        $totalAudits = $this->totalAudits($excludeConsole, $selectedUser, $selectedEventBase);
        $totalPages = ceil($totalAudits / $limit);

        // Values for selects.
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
            'selectedUser' => $selectedUser
        ]);
    }
    private function logger()
    {
        $logger = StaticContainer::get(LoggerInterface::class);
        return $logger;
    }
}
