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

use Piwik\Plugins\RebelAuditLog\AuditService;
use Piwik\Plugins\RebelAuditLog\Utility;

class UserInvited implements EventHandlerInterface
{
    private AuditService $auditService;
    private Utility $utility;

    public function __construct(AuditService $auditService, Utility $utility)
    {
        $this->auditService = $auditService;
        $this->utility = $utility;
    }

    public static function getSubscribedEvents(): array
    {
        return ['UsersManager.inviteUser.end'];
    }

    public function __invoke(...$params): void
    {
        $user = $this->utility->getUser();
        $name = $params[0];
        $email = $params[1];

        $log = "User {$name} ({$email}) invited.";

        $this->auditService->logAudit(
            'UsersManager',
            'invitedUser.end',
            $user,
            $this->utility->getIP(),
            $this->utility->session(),
            $log
        );
    }
}