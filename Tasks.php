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
use Piwik\Date;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->daily('deleteOldEntries', null, self::LOW_PRIORITY);
    }

    /**
     * Deletes the entries that are older than the system setting keeps them, and logs that.
     */
    public function deleteOldEntries(): void
    {
        $months = StaticContainer::get(SystemSettings::class)->getKeepMonths();
        if ($months === 0) {
            return;
        }

        $auditLog = new AuditLog();
        $day = Date::today()->subMonth($months);
        $deleted = $auditLog->deleteOlderThan($day);

        if ($deleted > 0) {
            (new EventLogger($auditLog))->log('RebelAuditLog', 'deleteOldEntries', "Deleted entries from before {$day->toString()}: $deleted.");
        }
    }
}
