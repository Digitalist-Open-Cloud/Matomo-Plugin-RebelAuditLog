<?php

namespace Piwik\Plugins\RebelAuditLog;

use Piwik\Common;
use Piwik\IP;
use Piwik\Piwik;
use Piwik\Request;
use Piwik\Plugins\UsersManager\API as APIUsersManager;
use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Utility
{
    public function getUser(): string
    {
        if (Common::isRunningConsoleCommand()) {
            return 'Console';
        } else {
            $user = APIUsersManager::getInstance()->getUser(Piwik::getCurrentUserLogin());
            if (is_array($user)) {
                if (!isset($user['login'])) {
                    return 'anonymous';
                }
                return $user['login'];
            }
            return $user;
        }
    }

    public function getLogin(): string
    {
        $login = StaticContainer::get(\Piwik\Auth::class)->getLogin();
        if (empty($login) || $login == 'anonymous') {
            $login = Request::fromRequest('form_login', false);
            if (Piwik::getAction() === 'logme') {
                $login = Request::fromRequest('login', $login);
            }
        }
        return $login;
    }

    public function getIP(): string
    {
        return IP::getIpFromHeader();
    }

    public function session(): string
    {
        return ''; // TODO: Implement session fetching later.
    }

    public function extractEventDetails($event): array
    {
        return [
            'params' => $event['parameters'],
            'module' => $event['module'],
            'action' => $event['action'],
        ];
    }

    public function logger()
    {
        $logger = StaticContainer::get(LoggerInterface::class);
        return $logger;
    }
}
