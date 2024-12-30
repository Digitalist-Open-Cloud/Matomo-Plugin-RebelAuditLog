<?php declare(strict_types=1);

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

namespace Piwik\Plugins\RebelAuditLog\Events;

use Piwik\Plugins\RebelAuditLog\Events;
use Piwik\Plugins\RebelAuditLog\Events\AbstractEventHandler;

class CoreAdminHomeInvalidateArchivedReports extends AbstractEventHandler
{
    public static function getSubscribedEvents(): array
    {
        return [Events::CORE_ADMIN_HOME_INVALIDATE_ARCHIVED_REPORTS];
    }

    public function __invoke(...$params): void
    {

        $details = $this->utility->extractEventDetails($params[1]);


        //$log = "bar  {$details['params']}";
        //$log = "Reports invalidated {$keys}";
        $detailedLog = $this->utility->getDetails($details['params']);

        $dates = $this->utility->truncate($detailedLog['dates'], 24);
        $log = "Archives invalidated for site id: {$detailedLog['idSites']} and dates {$dates}.";
        // $details = [
        //     'idSite' => $params['idSites'],
        //     'dates' => $params['dates'],
        // ];

        $this->logAudit($details['module'], $details['action'], $log, $detailedLog);
    }
}
