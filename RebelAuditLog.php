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

use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Log\LoggerInterface;
use Piwik\IP;
use Piwik\Plugin;
use Piwik\Request;
use Piwik\Db;
use Piwik\Common;
use Exception;
use Piwik\Plugins\UsersManager\API as APIUsersManager;

class RebelAuditLog extends Plugin
{
    private function getDb()
    {
        return Db::get();
    }

    private function extractEventDetails($event)
    {
        return [
            'params' => $event['parameters'],
            'module' => $event['module'],
            'action' => $event['action'],
        ];
    }

    public function registerEvents()
    {
        $events = [
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Login.authenticate.successful' => 'authenticated',
            'Login.authenticate.failed' => 'failedLogin',
            'PluginManager.pluginActivated' => 'pluginActivated',
            'PluginManager.pluginDeactivated' => 'pluginDeactivated',
            'PluginManager.pluginInstalled' => 'pluginInstalled',
            'PluginManager.pluginUninstalled' => 'pluginUninstalled',
            'API.SitesManager.addSite.end' => 'siteAdded',
            'API.SitesManager.updateSite.end' => 'siteUpdated',
            'API.SitesManager.deleteSite.end' => 'siteDeleted'
        ];
        return $events;
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'RebelAuditLog_RebelAuditLog';
    }

    public function install()
    {
        $db = $this->getDb();
        $query = "CREATE TABLE " . Common::prefixTable('rebel_audit') . " (
            `id` int(24) NOT NULL AUTO_INCREMENT,
            `event_base` varchar(255) NOT NULL,
            `event_task` varchar(255) NOT NULL,
            `user` varchar(100) NOT NULL,
            `ip` text,
            `session` varchar(191) NULL,
            `audit_log` varchar(512) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
            )
            ";
        try {
            $db->exec($query);
        } catch (Exception $e) {
            if (!$db->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    public function uninstall()
    {
        Db::dropTables(Common::prefixTable('rebel_audit'));
    }

    public function authenticated($auth)
    {
        $login = $this->getLogin();
        $this->logger()->warning('Login: ' . $login);
        $this->logger()->warning('IP ' . $this->getIP());
        $log = "User $login logged in successful";

        $this->addAudit('Login', 'authenticate.successful', $login, $this->getIP(), $this->session(), $log);
    }

    public function failedLogin($auth)
    {
        $login = $this->getLogin();
        $this->logger()->warning('Login failed: ' . $login);
        $this->logger()->warning('IP: ' . $this->getIP());
        $log = "User $login logged in failed";
        $this->addAudit('Login', 'authenticate.failed', $login, $this->getIP(), $this->session(), $log);
    }

    public function pluginActivated(string $pluginName): void
    {
        $log = "Plugin $pluginName activated";
        $login = $this->getUser();
        $this->addAudit(
            'PluginManager',
            'pluginActivated',
            $login,
            $this->getIP(),
            $this->session(),
            $log
        );
    }

    public function pluginDeactivated(string $pluginName): void
    {
        $log = "Plugin $pluginName deactivated";
        $login = $this->getUser();
        $this->addAudit(
            'PluginManager',
            'pluginDeactivated',
            $login,
            $this->getIP(),
            $this->session(),
            $log
        );
    }

    public function pluginInstalled(string $pluginName): void
    {
        $log = "Plugin $pluginName installed";
        $login = $this->getUser();
        $this->addAudit(
            'PluginManager',
            'pluginInstalled',
            $login,
            $this->getIP(),
            $this->session(),
            $log
        );
    }

    public function pluginUninstalled(string $pluginName): void
    {
        $log = "Plugin $pluginName uninstalled";
        $login = $this->getUser();
        $this->addAudit(
            'PluginManager',
            'pluginUninstalled',
            $login,
            $this->getIP(),
            $this->session(),
            $log
        );
    }

    public function siteAdded($null, $event)
    {
        $details = $this->extractEventDetails($event);
        $log = "Site  {$details['params']['siteName']} added";
        $login = $this->getUser();
        $this->addAudit(
            $details['module'],
            $details['action'],
            $login,
            $this->getIP(),
            $this->session(),
            $log
        );
    }

    public function siteUpdated($null, $event)
    {
        $details = $this->extractEventDetails($event);
        $log = "Site  {$details['params']['siteName']}, id {$details['params']['idSite']}, updated";
        $login = $this->getUser();
        $this->addAudit(
            $details['module'],
            $details['action'],
            $login,
            $this->getIP(),
            $this->session(),
            $log
        );
    }

    public function siteDeleted($null, $event)
    {
        $details = $this->extractEventDetails($event);
        $log = "Site id {$details['params']['idSite']} deleted";
        $login = $this->getUser();
        $this->addAudit(
            $details['module'],
            $details['action'],
            $login,
            $this->getIP(),
            $this->session(),
            $log
        );
    }

    private function getUser()
    {
        if (Common::isRunningConsoleCommand()) {
            $user = 'Console';
        } else {
            $user = APIUsersManager::getInstance()->getUser(Piwik::getCurrentUserLogin());
        }
        if (is_array($user)) {
            return $user['login'];
        }
        return $user;
    }
    private function getLogin()
    {
        $login = StaticContainer::get(\Piwik\Auth::class)->getLogin();
        if (empty($login) || $login == 'anonymous') {
            $login = $request = Request::fromRequest('form_login', false);
            if (Piwik::getAction() === 'logme') {
                $login = $request = Request::fromRequest('login', $login);
            }
        }
        return $login;
    }

    private function getIP()
    {
        return IP::getIpFromHeader();
    }

    private function logger()
    {
        $logger = StaticContainer::get(LoggerInterface::class);
        return $logger;
    }

    private function addAudit($eventBase, $eventTask, $user, $ip, $session, $log)
    {
        $query = "INSERT INTO `" . Common::prefixTable('rebel_audit') . "`
        (event_base, event_task, user, ip, session, audit_log) VALUES (?,?,?,?,?,?)";
        $params = [$eventBase, $eventTask, $user, $ip, $session, $log];
        $db = $this->getDb();
        $db->query($query, $params);
    }

    /**
     * Later on, try to get session.
     * For now, this returns empty.
     * @return string
     */
    private function session()
    {
        return '';
    }
}
