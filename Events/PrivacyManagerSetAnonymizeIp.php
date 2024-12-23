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

class PrivacyManagerSetAnonymizeIp extends AbstractEventHandler
{
    public static function getSubscribedEvents(): array
    {
        return [Events::PRIVACY_MANAGER_SET_ANONYMIZE_IP];
    }

    public function __invoke(...$params): void
    {
        $details = $this->utility->extractEventDetails($params[1]);

        if ($details['params']['anonymizeIPEnable'] == "1") {
            $log = "Anonymize IP is turned on.";
        } else {
            $log = "Anonymize IP is turned off.";
        }
        if ($details['params']['anonymizeUserId'] == "1") {
            $log .= " Anonymize user id is turned on.";
        }
        if ($details['params']['forceCookielessTracking'] == "1") {
            $log .= " Force Cookieless tracking is turned on.";
        }
        if ($details['params']['useAnonymizedIpForVisitEnrichment'] == "1") {
            $log .= " Use Anonymized IP addresses when enriching visits is turned on.";
        }
        if ($details['params']['anonymizeReferrer']) {
            $log .= " Anonymize referer is set to {$details['params']['anonymizeReferrer']}";
        }
        $detailedLog = $this->utility->getDetails($details['params']);


        $this->logAudit($details['module'], $details['action'], $log, $detailedLog);
    }
}
