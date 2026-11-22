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
use Piwik\Plugin;

class RebelAuditLog extends Plugin
{
    public function registerEvents()
    {
        $events = (new EventLogger(new AuditLog()))->getEventHandlers();
        $events['Db.getTablesInstalled'] = 'getTablesInstalled';
        $events['AssetManager.getStylesheetFiles'] = 'getStylesheetFiles';

        return $events;
    }

    public function install()
    {
        (new AuditLog())->install();
    }

    public function uninstall()
    {
        (new AuditLog())->uninstall();
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/RebelAuditLog/stylesheets/auditLog.less';
        $stylesheets[] = 'plugins/RebelAuditLog/stylesheets/menuIcon.less';
    }

    public function getTablesInstalled(&$allTablesInstalled)
    {
        $allTablesInstalled[] = Common::prefixTable(AuditLog::TABLE);
        $allTablesInstalled[] = Common::prefixTable(AuditLog::DETAILS_TABLE);
    }
}
