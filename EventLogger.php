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
use Piwik\Container\StaticContainer;
use Piwik\IP;
use Piwik\Log\LoggerInterface;
use Piwik\Piwik;
use Piwik\Settings\FieldConfig;

/**
 * Listens to the events of Matomo that are worth an entry in the audit log, and writes it.
 */
class EventLogger
{
    // What is logged in place of a secret.
    public const MASK = '***';

    // API method => what is logged when it has run. {name} stands for the parameter "name" of
    // the call. A method that needs more than that has an entry in DESCRIBED_API_CALLS.
    public const API_MESSAGES = [
        'Annotations.add' => 'Added annotation {note} for site id {idSite}.',
        'Annotations.delete' => 'Deleted an annotation for site id {idSite}.',
        'Annotations.save' => 'Saved changes for annotation {note} for site id {idSite}.',
        'CoreAdminHome.invalidateArchivedReports' => 'Archives invalidated for site id: {idSites} and dates {dates}.',
        'CustomAlerts.addAlert' => 'Added custom alert {name} for site(s) {idSites}.',
        'CustomAlerts.deleteAlert' => 'Deleted custom alert {idAlert}.',
        'CustomAlerts.editAlert' => 'Edited custom alert {name} for site(s) {idSites}.',
        'CustomDimensions.configureExistingCustomDimension' => 'Configured custom dimension {name} for site {idSite}.',
        'CustomDimensions.configureNewCustomDimension' => 'Added new custom dimension {name} for site {idSite}.',
        'Dashboard.copyDashboardToUser' => 'Copied dashboard {dashboardName} to user {copyToUser}.',
        'Dashboard.createNewDashboardForUser' => 'Added new dashboard {dashboardName}.',
        'Dashboard.removeDashboard' => 'Removed dashboard with id {idDashboard}.',
        'Dashboard.resetDashboardLayout' => 'Layout was reset for dashboard with id {idDashboard}.',
        'Goals.addGoal' => 'Added new Goal {name} for site {idSite}.',
        'Goals.deleteGoal' => 'Deleted Goal with id {idGoal} for site {idSite}.',
        'Goals.updateGoal' => 'Updated Goal {name} for site {idSite}.',
        'GroupPermissions.addUserToGroup' => 'User {login} added to group with id {idGroup}.',
        'GroupPermissions.createGroup' => 'Group {groupName} created.',
        'GroupPermissions.deleteGroup' => 'Group with id {idGroup} deleted.',
        'GroupPermissions.removeUserFromGroup' => 'User {login} removed from group with id {idGroup}.',
        'GroupPermissions.renameGroup' => 'Group with id {idGroup} renamed to {newName}.',
        'GroupPermissions.setGroupAccess' => 'Set group {name} to access {access} for sites {idSites}.',
        'PrivacyManager.activateDoNotTrack' => 'Do Not Track enabled.',
        'PrivacyManager.anonymizeSomeRawData' => 'Anonymized raw data for sites {idSites} between dates {date}.',
        'PrivacyManager.deactivateDoNotTrack' => 'Do Not Track disabled.',
        'PrivacyManager.deleteDataSubjects' => 'Deleted data with GDPR Tools.',
        'PrivacyManager.executeDataPurge' => 'Data purge executed.',
        'PrivacyManager.exportDataSubjects' => 'Exported data with GDPR Tools.',
        'PrivacyManager.setAnonymizeIpSettings' => '',
        'PrivacyManager.setDeleteLogsSettings' => 'Set to delete logs older than {deleteLogsOlderThan} days.',
        'PrivacyManager.setDeleteReportsSettings' => 'Set to delete reports older than {deleteReportsOlderThan} months.',
        'PrivacyManager.setScheduleReportDeletionSettings' => '',
        'SegmentEditor.add' => 'Added segment {name} for site {idSite}.',
        'SegmentEditor.delete' => 'Deleted segment with id {idSegment}.',
        'SegmentEditor.update' => 'Updated segment {name} for site {idSite}.',
        'SitesManager.addSite' => 'Site {siteName} added.',
        'SitesManager.deleteSite' => 'Site id {idSite} deleted.',
        'SitesManager.updateSite' => 'Site {siteName}, id {idSite}, updated.',
        'TagManager.addContainer' => 'Added Tag manager container {name} for site id {idSite}.',
        'TagManager.addContainerTag' => 'Added container tag {name} for site id {idSite}.',
        'TagManager.addContainerTrigger' => 'Added container trigger {name} of type {type} for site id {idSite}.',
        'TagManager.addContainerVariable' => 'Added container variable {name} of type {type} for site id {idSite}.',
        'TagManager.deleteContainer' => 'Deleted container with id {idContainer} for site id {idSite}.',
        'TagManager.deleteContainerTag' => 'Deleted container tag ({idTag}) for container with id {idContainer} for site id {idSite}.',
        'TagManager.deleteContainerTrigger' => 'Deleted container trigger ({idTrigger}) for container with id {idContainer} for site id {idSite}.',
        'TagManager.deleteContainerVariable' => 'Deleted container variable ({idVariable}) for container id {idContainer} for site id {idSite}.',
        'TagManager.deleteContainerVersion' => 'A container version was deleted for container id {idContainer} for site id {idSite}.',
        'TagManager.exportContainerVersion' => 'A container version was exported for container id {idContainer} for site id {idSite}.',
        'TagManager.importContainerVersion' => 'A container version was imported for container id {idContainer} for site id {idSite}.',
        'TagManager.pauseContainerTag' => 'Paused container tag ({idTag}) for site id {idSite}.',
        'TagManager.publishContainerVersion' => 'A container version was published for container id {idContainer} in environment {environment} for site id {idSite}.',
        'TagManager.resumeContainerTag' => 'Resumed container tag ({idTag}) for site id {idSite}.',
        'TagManager.updateContainer' => 'Updated container {name} for site id {idSite}.',
        'TagManager.updateContainerTag' => 'Updated container tag {name} ({idTag}) for site id {idSite}.',
        'TagManager.updateContainerTrigger' => 'Updated container trigger {name} for container with id {idContainer} for site id {idSite}.',
        'TagManager.updateContainerVariable' => 'Updated container variable {name} for site id {idSite}.',
        'TagManager.updateContainerVersion' => 'A container version was updated for container id {idContainer} for site id {idSite}.',
        'UsersManager.addCapabilities' => 'Changed capabilities of user {userLogin}.',
        'UsersManager.setSuperUserAccess' => '',
        'UsersManager.setUserAccess' => 'Changed access of user {userLogin} to {access} for site id {idSites}.',
    ];

    // API method => the method of this class that tells what is logged, from the parameters.
    private const DESCRIBED_API_CALLS = [
        'PrivacyManager.setAnonymizeIpSettings' => 'describeAnonymizeIpSettings',
        'PrivacyManager.setScheduleReportDeletionSettings' => 'describeReportDeletionSchedule',
        'UsersManager.setSuperUserAccess' => 'describeSuperUserAccess',
    ];

    // Event => the method of this class that logs it.
    public const OTHER_EVENTS = [
        'BotTracker.deleteBot.successful' => 'logBotDeleted',
        'BotTracker.insertBot.successful' => 'logBotInserted',
        'BotTracker.updateBot.successful' => 'logBotUpdated',
        'Core.configFileChanged' => 'logConfigFileChanged',
        'Login.authenticate.failed' => 'logLoginFailed',
        'Login.authenticate.successful' => 'logLoginSuccessful',
        'PluginManager.pluginActivated' => 'logPluginActivated',
        'PluginManager.pluginDeactivated' => 'logPluginDeactivated',
        'PluginManager.pluginInstalled' => 'logPluginInstalled',
        'PluginManager.pluginUninstalled' => 'logPluginUninstalled',
        'SystemSettings.updated' => 'logSystemSettingsUpdated',
        'TagManager.containerFileChanged' => 'logContainerFileChanged',
        'TagManager.containerFileDeleted' => 'logContainerFileDeleted',
        'UsersManager.addUser.end' => 'logUserAdded',
        'UsersManager.inviteUser.end' => 'logUserInvited',
        'VisitorGenerator.VisitsFake.trackVisit' => 'logFakeVisit',
    ];

    // Names of values that are never logged: passwords, tokens and keys.
    private const SECRET_NAME = '/pass(word|wd|phrase)?|pwd|token|secret|credential|keys?$|salt|hash|md5|signature|cookie|session|licen[sc]e|otp|auth(?!or(?!i))/i';

    private const MAX_LENGTH_IN_MESSAGE = 100;

    private AuditLog $auditLog;

    public function __construct(AuditLog $auditLog)
    {
        $this->auditLog = $auditLog;
    }

    /**
     * @return array<string, callable> by event name, what logs the event
     */
    public function getEventHandlers(): array
    {
        $handlers = [];

        foreach (array_keys(self::API_MESSAGES) as $apiMethod) {
            $handlers["API.$apiMethod.end"] = $this->makeSafe("API.$apiMethod.end", function ($returnedValue = null, $call = null) use ($apiMethod) {
                $this->logApiCall($apiMethod, is_array($call) ? $call : []);
            });
        }
        foreach (self::OTHER_EVENTS as $event => $method) {
            $handlers[$event] = $this->makeSafe($event, [$this, $method]);
        }

        return $handlers;
    }

    /**
     * @param array<string, mixed> $details more about the action. Empty values are left out and
     *                                      secrets are masked.
     * @param string $user who did it, by default the current user
     */
    public function log(string $eventBase, string $eventTask, string $message, array $details = [], string $user = ''): void
    {
        if ($user === '') {
            $user = Common::isRunningConsoleCommand() ? AuditLog::CONSOLE_USER : (Piwik::getCurrentUserLogin() ?: 'anonymous');
        }

        $this->auditLog->add($eventBase, $eventTask, $user, IP::getIpFromHeader(), null, $message, $this->toDetails($details));
    }

    public function logBotDeleted($idSite = '', $idBot = ''): void
    {
        $this->log('BotTracker', 'deleteBot.successful', "Bot with id {$this->toText($idBot)} deleted for site {$this->toText($idSite)}.");
    }

    public function logBotInserted($idSite = '', $bot = []): void
    {
        $bot = (array) $bot;
        $this->log('BotTracker', 'insertBot.successful', "Bot {$this->toText($bot['botName'] ?? '')} added for site {$this->toText($idSite)}.", ['idSite' => $idSite] + $bot);
    }

    public function logBotUpdated($idSite = '', $bot = []): void
    {
        $bot = (array) $bot;
        $this->log('BotTracker', 'updateBot.successful', "Bot {$this->toText($bot['botName'] ?? '')} updated for site {$this->toText($idSite)}.", ['idSite' => $idSite] + $bot);
    }

    public function logConfigFileChanged($file = ''): void
    {
        $this->log('Core', 'configFileChanged', "Config file has changed at {$this->toText($file)}.", ['file' => $file]);
    }

    public function logLoginFailed($login = ''): void
    {
        $login = $this->toText($login);
        $this->log('Login', 'authenticate.failed', "Login of user $login failed.", [], $login);
    }

    public function logLoginSuccessful($login = ''): void
    {
        $login = $this->toText($login);
        $this->log('Login', 'authenticate.successful', "User $login logged in successfully.", [], $login);
    }

    public function logPluginActivated($plugin = ''): void
    {
        $this->log('PluginManager', 'pluginActivated', "Plugin {$this->toText($plugin)} activated.");
    }

    public function logPluginDeactivated($plugin = ''): void
    {
        $this->log('PluginManager', 'pluginDeactivated', "Plugin {$this->toText($plugin)} deactivated.");
    }

    public function logPluginInstalled($plugin = ''): void
    {
        $this->log('PluginManager', 'pluginInstalled', "Plugin {$this->toText($plugin)} installed.");
    }

    public function logPluginUninstalled($plugin = ''): void
    {
        $this->log('PluginManager', 'pluginUninstalled', "Plugin {$this->toText($plugin)} uninstalled.");
    }

    /**
     * @param \Piwik\Settings\Plugin\SystemSettings|null $settings
     */
    public function logSystemSettingsUpdated($settings = null): void
    {
        if (!$settings) {
            return;
        }

        $values = [];
        foreach ($settings->getSettingsWritableByCurrentUser() as $setting) {
            $isPassword = $setting->configureField()->uiControl === FieldConfig::UI_CONTROL_PASSWORD;
            $values[$setting->getName()] = $isPassword && !empty($setting->getValue()) ? self::MASK : $setting->getValue();
        }

        $plugin = $settings->getPluginName();
        $this->log('SystemSettings', 'updated', "System settings of plugin $plugin updated.", ['plugin' => $plugin] + $values);
    }

    public function logContainerFileChanged($file = ''): void
    {
        $this->log('TagManager', 'containerFileChanged', "Container file {$this->toText($file)} changed.");
    }

    public function logContainerFileDeleted($file = ''): void
    {
        $this->log('TagManager', 'containerFileDeleted', "Container file {$this->toText($file)} was deleted.");
    }

    public function logUserAdded($login = '', $email = ''): void
    {
        $this->log('UsersManager', 'addUser.end', "User {$this->toText($login)} ({$this->toText($email)}) added.");
    }

    public function logUserInvited($login = '', $email = ''): void
    {
        $this->log('UsersManager', 'inviteUser.end', "User {$this->toText($login)} ({$this->toText($email)}) invited.");
    }

    /**
     * @param object|null $tracker the tracker of the VisitorGenerator plugin
     */
    public function logFakeVisit($tracker = null): void
    {
        $details = [
            'idSite' => $tracker->idSite ?? null,
            'pageUrl' => $tracker->pageUrl ?? null,
            'visitorId' => $tracker->visitorId ?? null,
            'ip' => $tracker->ip ?? null,
        ];
        $this->log(
            'VisitorGenerator',
            'VisitsFake.trackVisit',
            "Fake visit generated with url: {$this->toText($details['pageUrl'])} for site id {$this->toText($details['idSite'])}.",
            $details
        );
    }

    /**
     * @param array $call the description of the call that the "API.<plugin>.<method>.end"
     *                    event passes on: module, action and parameters
     */
    private function logApiCall(string $apiMethod, array $call): void
    {
        [$module, $action] = explode('.', $apiMethod);
        $parameters = is_array($call['parameters'] ?? null) ? $call['parameters'] : [];

        if (isset(self::DESCRIBED_API_CALLS[$apiMethod])) {
            $message = $this->{self::DESCRIBED_API_CALLS[$apiMethod]}($parameters);
        } else {
            $message = preg_replace_callback('/\{(\w+)\}/', function (array $match) use ($parameters) {
                $value = $parameters[$match[1]] ?? '';

                return $this->isSecretName($match[1]) ? self::MASK : $this->toText($value);
            }, self::API_MESSAGES[$apiMethod]);
        }

        $this->log($module, $action, $message, $parameters);
    }

    private function describeAnonymizeIpSettings(array $parameters): string
    {
        $message = 'Anonymize IP is turned ' . (!empty($parameters['anonymizeIPEnable']) ? 'on' : 'off') . '.';
        if (!empty($parameters['anonymizeUserId'])) {
            $message .= ' Anonymize user id is turned on.';
        }
        if (!empty($parameters['forceCookielessTracking'])) {
            $message .= ' Force Cookieless tracking is turned on.';
        }
        if (!empty($parameters['useAnonymizedIpForVisitEnrichment'])) {
            $message .= ' Use Anonymized IP addresses when enriching visits is turned on.';
        }
        if (!empty($parameters['anonymizeReferrer'])) {
            $message .= " Anonymize referrer is set to {$this->toText($parameters['anonymizeReferrer'])}.";
        }

        return $message;
    }

    private function describeReportDeletionSchedule(array $parameters): string
    {
        $intervals = ['1' => 'day', '7' => 'week'];
        $interval = $intervals[(string) ($parameters['deleteLowestInterval'] ?? '')] ?? 'month';

        return "Schedule old data deletion to every $interval.";
    }

    private function describeSuperUserAccess(array $parameters): string
    {
        $login = $this->toText($parameters['userLogin'] ?? '');

        return !empty($parameters['hasSuperUserAccess']) ? "$login was added as super user." : "$login was removed as super user.";
    }

    /**
     * An action must not fail because it could not be logged, so a failure is reported to
     * Matomo's log instead.
     */
    private function makeSafe(string $event, callable $handler): callable
    {
        return function (...$params) use ($event, $handler): void {
            try {
                $handler(...$params);
            } catch (\Throwable $e) {
                StaticContainer::get(LoggerInterface::class)->error(
                    'RebelAuditLog: the event {event} could not be logged: {message}',
                    ['event' => $event, 'message' => $e->getMessage()]
                );
            }
        };
    }

    private function isSecretName(string $name): bool
    {
        return (bool) preg_match(self::SECRET_NAME, $name);
    }

    /**
     * @param mixed $value
     * @return string the value as a part of a message
     */
    private function toText($value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : (string) json_encode($item), $value));
        } elseif (!is_scalar($value)) {
            $value = '';
        }
        $value = (string) $value;

        return mb_strlen($value) > self::MAX_LENGTH_IN_MESSAGE ? mb_substr($value, 0, self::MAX_LENGTH_IN_MESSAGE - 3) . '...' : $value;
    }

    /**
     * Returns the values as strings to store: lists and structures as JSON, anything whose name
     * tells that it is a secret masked, also inside them, and empty values left out.
     *
     * @return array<string, string>
     */
    private function toDetails(array $values): array
    {
        $details = [];
        foreach ($this->maskSecrets($values) as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
            if ($value !== null && $value !== '' && $value !== false && $value !== '[]') {
                $details[(string) $key] = (string) $value;
            }
        }

        return $details;
    }

    private function maskSecrets(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSecretName($key) && !empty($value)) {
                $values[$key] = self::MASK;
            } elseif (is_array($value)) {
                $values[$key] = $this->maskSecrets($value);
            }
        }

        // Settings are also sent as a list of names with their values.
        if (isset($values['name'], $values['value']) && is_string($values['name']) && $this->isSecretName($values['name'])) {
            $values['value'] = self::MASK;
        }

        return $values;
    }
}
