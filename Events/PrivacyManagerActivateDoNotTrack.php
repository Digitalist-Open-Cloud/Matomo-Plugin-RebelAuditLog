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

class PrivacyManagerActivateDoNotTrack extends AbstractEventHandler
{
    public static function getSubscribedEvents(): array
    {
        return [Events::PRIVACY_MANAGER_ACTIVATE_DO_NOT_TRACK];
    }

    public function __invoke(...$params): void
    {

        $details = $this->utility->extractEventDetails($params[1]);
        $log = "Do Not Track enabled.";

        $this->logAudit($details['module'], $details['action'], $log);
    }
}
