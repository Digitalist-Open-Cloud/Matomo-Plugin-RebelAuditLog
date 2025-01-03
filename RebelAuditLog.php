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

use Piwik\Plugin;
use Piwik\Db;
use Piwik\Common;
use Exception;
use Piwik\Plugins\RebelAuditLog\EventHandlerFactory;
use Piwik\Plugins\RebelAuditLog\AuditService;
use Piwik\Plugins\RebelAuditLog\Utility;
use Piwik\Plugins\RebelAuditLog\Events;

class RebelAuditLog extends Plugin
{
    private EventHandlerFactory $eventHandlerFactory;

    public function __construct()
    {
        parent::__construct();
        $auditService = new AuditService();
        $utility = new Utility();
        $events = new Events();
        $this->eventHandlerFactory = new EventHandlerFactory($auditService, $utility, $events);
    }

    private function getDb()
    {
        return Db::get();
    }

    public function registerEvents()
    {
        return $this->eventHandlerFactory->discoverEventHandlers();
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
            `audit_log` varchar(1024) NOT NULL,
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
        // Create indexes for matomo_rebel_audit
        try {
            $db->exec("CREATE INDEX idx_user ON " . Common::prefixTable('rebel_audit') . "(`user`)");
            $db->exec("CREATE INDEX idx_event_base ON " . Common::prefixTable('rebel_audit') . "(`event_base`)");
            $db->exec("CREATE INDEX idx_user_eventbase_timestamp ON " . Common::prefixTable('rebel_audit') . "(`user`, `event_base`, `timestamp`)");
            $db->exec("CREATE INDEX idx_timestamp ON " . Common::prefixTable('rebel_audit') . "(`timestamp`)");
        } catch (Exception $e) {
            if (!$db->isErrNo($e, '1061')) { // Ignore "duplicate key name" error (1061 in MySQL)
                throw $e;
            }
        }
        $query = "CREATE TABLE " . Common::prefixTable('rebel_audit_details') . " (
            `id` int(24) NOT NULL AUTO_INCREMENT,
            `base_id` INT(24) NOT NULL,
            `key` varchar(512) NOT NULL,
            `value` text NOT NULL,
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
        // Create indexes for matomo_rebel_audit_details
        try {
            $db->exec("CREATE INDEX idx_base_id ON " . Common::prefixTable('rebel_audit_details') . "(`base_id`)");
            $db->exec("CREATE INDEX idx_key ON " . Common::prefixTable('rebel_audit_details') . "(`key`)");
            $db->exec("CREATE INDEX idx_base_id_key ON " . Common::prefixTable('rebel_audit_details') . "(`base_id`, `key`)");
        } catch (Exception $e) {
            if (!$db->isErrNo($e, '1061')) { // Ignore "duplicate key name" error (1061 in MySQL)
                throw $e;
            }
        }
    }

    public function uninstall()
    {
        Db::dropTables(Common::prefixTable('rebel_audit'));
        Db::dropTables(Common::prefixTable('rebel_audit_details'));
    }
}
