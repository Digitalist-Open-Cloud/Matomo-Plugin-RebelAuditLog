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
        }
        $user = APIUsersManager::getInstance()->getUser(Piwik::getCurrentUserLogin());
        return is_array($user) ? ($user['login'] ?? 'anonymous') : 'anonymous';
    }

    public function getLogin(): string
    {
        $login = StaticContainer::get(\Piwik\Auth::class)->getLogin();
        if (empty($login) || $login === 'anonymous') {
            $login = Piwik::getAction() === 'logme'
                ? Request::fromRequest('login', Request::fromRequest('form_login', false))
                : Request::fromRequest('form_login', false);
        }
        return $login;
    }

    public function getIP(): string
    {
        return IP::getIpFromHeader();
    }

    /**
     * @return string
     */
    public function session(): string
    {
        return ''; // TODO: Implement session fetching later.
    }

    /**
     * @return array
     */
    public function extractEventDetails($event): array
    {
        if (isset($event['module'])) {
            $module = $event['module'];
        } else {
            $module = '';
        }
        if (isset($event['action'])) {
            $action = $event['action'];
        } else {
            $action = '';
        }
        if (isset($event['parameters'])) {
            $parameters = $event['parameters'];
        } else {
            $parameters = '';
        }
        return [
            'params' => $parameters,
            'module' => $module,
            'action' => $action,
        ];
    }

    public function logger()
    {
        return StaticContainer::get(LoggerInterface::class);
    }

    /**
     * @return array
     */
    public function removeEmpty(array $array): array
    {
        return array_filter($array, function ($value) {
            return $this->removeEmptyInternal($value);
        });
    }

    /**
     * @return bool
     */
    private function removeEmptyInternal($value): bool
    {
        return !empty($value) || $value === 0;
    }

    /**
     * Get details.
     * @return array
     */
    public function getDetails(array $params): array
    {
        $logDetails = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $logDetails[$key] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $logDetails[$key] = (string)$value;
            }
        }
        return $logDetails;
    }
    public function truncate($string, $length, $dots = "...") {
        return (strlen($string) > $length) ? substr($string, 0, $length - strlen($dots)) . $dots : $string;
    }
}
