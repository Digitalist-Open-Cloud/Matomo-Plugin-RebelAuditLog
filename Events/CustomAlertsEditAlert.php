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

namespace Piwik\Plugins\RebelAuditLog\Events;

use Piwik\Plugins\RebelAuditLog\Events;
use Piwik\Plugins\RebelAuditLog\Events\AbstractEventHandler;

class CustomAlertsEditAlert extends AbstractEventHandler
{
    public static function getSubscribedEvents(): array
    {
        return [Events::CUSTOM_ALERTS_EDIT_ALERT];
    }

    public function __invoke(...$params): void
    {

        $details = $this->utility->extractEventDetails($params[1]);
        if (isset($details['params']['idSites'])) {
            foreach ($details['params']['idSites'] as $id) {
                $sites[] = $id;
            }
            $siteIds = implode(", ", $sites);
        }
        $log = "Edited custom alert {$details['params']['name']} for site(s) {$siteIds}.";
        $detailedLog = $this->utility->getDetails($details['params']);

        $this->logAudit($details['module'], $details['action'], $log, $detailedLog);
    }
}
