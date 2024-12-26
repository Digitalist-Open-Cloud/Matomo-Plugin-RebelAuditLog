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

use Piwik\Plugins\RebelAuditLog\Events\AbstractEventHandler;
use Piwik\Plugins\RebelAuditLog\Events;

class VisitorGeneratorVisitsFakeTrackVisit extends AbstractEventHandler
{
    public static function getSubscribedEvents(): array
    {
        return [Events::VISITOR_GENERATOR_VISITS_FAKE];
    }

    public function __invoke(...$params): void
    {
        $tracker = $params[0];
        $pageUrl = $tracker->pageUrl;
        $pageUrl = isset($tracker->pageUrl) ? $tracker->pageUrl : null;
        $idSite = isset($tracker->idSite) ? $tracker->idSite : null;
        $visitorId = isset($tracker->visitorId) ? $tracker->visitorId : null;
        $ip = isset($tracker->ip) ? $tracker->ip : null;
        $log = "Fake visit generated with url: {$pageUrl} for site id {$idSite}";
        $detailedLog = [
            'idSite' => $idSite,
            'pageUrl' => $pageUrl,
            'visitorId' => $visitorId,
            'ip' => $ip
        ];

        $this->logAudit('VisitorGenerator', 'VisitsFake.trackVisit', $log, array_filter($detailedLog));
    }
}
