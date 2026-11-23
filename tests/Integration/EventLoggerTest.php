<?php

namespace Piwik\Plugins\RebelAuditLog\tests\Integration;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Plugins\Login\SystemSettings as LoginSettings;
use Piwik\Plugins\RebelAuditLog\AuditLog;
use Piwik\Plugins\RebelAuditLog\EventLogger;
use Piwik\Plugins\RebelAuditLog\SystemSettings;
use Piwik\Plugins\RebelAuditLog\Tasks;
use Piwik\Plugins\UsersManager\Model as UsersManagerModel;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group RebelAuditLog
 * @group Plugins
 */
class EventLoggerTest extends IntegrationTestCase
{
    private AuditLog $auditLog;
    private int $idSite;

    protected static function configureFixture($fixture)
    {
        parent::configureFixture($fixture);

        $fixture->createSuperUser = true;
    }

    public function setUp(): void
    {
        parent::setUp();

        FakeAccess::clearAccess(true);
        $this->auditLog = new AuditLog();
        $this->idSite = Fixture::createWebsite('2024-01-01 00:00:00');
        // Setting up Matomo and the website is logged too.
        Db::query('TRUNCATE ' . Common::prefixTable(AuditLog::TABLE));
        Db::query('TRUNCATE ' . Common::prefixTable(AuditLog::DETAILS_TABLE));
    }

    public function provideContainerConfig()
    {
        return ['Piwik\Access' => new FakeAccess()];
    }

    public function testEveryMessageOnlyNamesParametersOfItsApiMethod()
    {
        $checked = 0;
        foreach (EventLogger::API_MESSAGES as $apiMethod => $message) {
            [$plugin, $method] = explode('.', $apiMethod);
            if (!Manager::getInstance()->isPluginLoaded($plugin)) {
                continue;
            }

            $this->assertTrue(method_exists("Piwik\\Plugins\\$plugin\\API", $method), "$apiMethod does not exist");
            $parameters = array_map(
                fn (\ReflectionParameter $parameter) => $parameter->getName(),
                (new \ReflectionMethod("Piwik\\Plugins\\$plugin\\API", $method))->getParameters()
            );
            preg_match_all('/\{(\w+)\}/', $message, $named);

            $this->assertSame([], array_values(array_diff($named[1], $parameters)), "The message of $apiMethod names unknown parameters");
            $checked++;
        }

        $this->assertGreaterThan(30, $checked);
    }

    public function testChangesToGoalsAreLogged()
    {
        $idGoal = $this->call('Goals.addGoal', ['idSite' => $this->idSite, 'name' => 'Signup', 'matchAttribute' => 'url', 'pattern' => '/signup', 'patternType' => 'contains']);
        $this->assertLastEntry('Goals', 'addGoal', "Added new Goal Signup for site {$this->idSite}.");

        $this->call('Goals.updateGoal', ['idSite' => $this->idSite, 'idGoal' => $idGoal, 'name' => 'Signed up', 'matchAttribute' => 'url', 'pattern' => '/signup', 'patternType' => 'contains']);
        $this->assertLastEntry('Goals', 'updateGoal', "Updated Goal Signed up for site {$this->idSite}.");

        $this->call('Goals.deleteGoal', ['idSite' => $this->idSite, 'idGoal' => $idGoal]);
        $entry = $this->assertLastEntry('Goals', 'deleteGoal', "Deleted Goal with id $idGoal for site {$this->idSite}.");
        $this->assertSame((string) $idGoal, $entry['idGoal']);
    }

    public function testChangesToWebsitesAreLoggedWithoutThePassword()
    {
        $idSite = $this->call('SitesManager.addSite', ['siteName' => 'Shop', 'urls' => ['https://shop.example']]);
        $entry = $this->assertLastEntry('SitesManager', 'addSite', 'Site Shop added.');
        $this->assertSame('["https://shop.example"]', $entry['urls']);

        $this->call('SitesManager.updateSite', ['idSite' => $idSite, 'siteName' => 'Web shop']);
        $this->assertLastEntry('SitesManager', 'updateSite', "Site Web shop, id $idSite, updated.");

        $this->call('SitesManager.deleteSite', ['idSite' => $idSite, 'passwordConfirmation' => Fixture::ADMIN_USER_PASSWORD]);
        $entry = $this->assertLastEntry('SitesManager', 'deleteSite', "Site id $idSite deleted.");
        $this->assertSame(EventLogger::MASK, $entry['passwordConfirmation']);
    }

    public function testChangesToUsersAndTheirAccessAreLogged()
    {
        $this->call('UsersManager.addUser', ['userLogin' => 'anna', 'password' => 'a-long-secret-1', 'email' => 'anna@example.org']);
        $entries = $this->getEntries();
        $this->assertContains('User anna (anna@example.org) added.', array_column($entries, 'audit_log'));
        $this->assertStringNotContainsString('a-long-secret-1', json_encode($entries));

        $access = ['userLogin' => 'anna', 'passwordConfirmation' => Fixture::ADMIN_USER_PASSWORD];
        $this->call('UsersManager.setUserAccess', $access + ['access' => 'write', 'idSites' => [$this->idSite]]);
        $entry = $this->assertLastEntry('UsersManager', 'setUserAccess', "Changed access of user anna to write for site id {$this->idSite}.");
        $this->assertSame(EventLogger::MASK, $entry['passwordConfirmation']);

        $this->call('UsersManager.setSuperUserAccess', $access + ['hasSuperUserAccess' => 1]);
        $this->assertLastEntry('UsersManager', 'setSuperUserAccess', 'anna was added as super user.');

        $this->call('UsersManager.setSuperUserAccess', $access + ['hasSuperUserAccess' => 0]);
        $this->assertLastEntry('UsersManager', 'setSuperUserAccess', 'anna was removed as super user.');
    }

    public function testAnnotationsSegmentsAndPrivacySettingsAreLogged()
    {
        $this->call('Annotations.add', ['idSite' => $this->idSite, 'date' => '2024-03-05', 'note' => 'Campaign start']);
        $this->assertLastEntry('Annotations', 'add', "Added annotation Campaign start for site id {$this->idSite}.");

        $this->call('SegmentEditor.add', ['name' => 'Returning', 'definition' => 'visitorType==returning', 'idSite' => $this->idSite]);
        $this->assertLastEntry('SegmentEditor', 'add', "Added segment Returning for site {$this->idSite}.");

        $this->call('PrivacyManager.deactivateDoNotTrack', []);
        $this->assertLastEntry('PrivacyManager', 'deactivateDoNotTrack', 'Do Not Track disabled.');

        $this->call('PrivacyManager.setAnonymizeIpSettings', ['anonymizeIPEnable' => 1, 'ipAddressMaskLength' => 2, 'useAnonymizedIpForVisitEnrichment' => 0, 'anonymizeUserId' => 1, 'anonymizeOrderId' => 0, 'anonymizeReferrer' => '', 'forceCookielessTracking' => 0]);
        $this->assertLastEntry('PrivacyManager', 'setAnonymizeIpSettings', 'Anonymize IP is turned on. Anonymize user id is turned on.');

        $this->call('CoreAdminHome.invalidateArchivedReports', ['idSites' => $this->idSite, 'dates' => '2024-03-05']);
        $this->assertLastEntry('CoreAdminHome', 'invalidateArchivedReports', "Archives invalidated for site id: {$this->idSite} and dates 2024-03-05.");
    }

    public function testAChangedPasswordIsLoggedWithoutThePassword()
    {
        Piwik::postEvent('UsersManager.updateUser.end', ['bo', true, 'bo@example.org', 'the-new-password']);

        $this->assertStringNotContainsString('the-new-password', json_encode($this->getEntries()));
    }

    public function testUsersCanBeDeletedAndLoseCapabilitiesAndThatIsLogged()
    {
        $this->call('UsersManager.addUser', ['userLogin' => 'anna', 'password' => 'a-long-secret-1', 'email' => 'anna@example.org']);
        $this->call('UsersManager.setUserAccess', ['userLogin' => 'anna', 'access' => 'write', 'idSites' => [$this->idSite], 'passwordConfirmation' => Fixture::ADMIN_USER_PASSWORD]);

        $this->call('UsersManager.removeCapabilities', ['userLogin' => 'anna', 'capabilities' => ['tagmanager_write'], 'idSites' => [$this->idSite]]);
        $this->assertLastEntry('UsersManager', 'removeCapabilities', "Removed capabilities tagmanager_write of user anna for site id {$this->idSite}.");

        $this->call('UsersManager.deleteUser', ['userLogin' => 'anna', 'passwordConfirmation' => Fixture::ADMIN_USER_PASSWORD]);
        $this->assertContains('User anna deleted.', array_column($this->getEntries(), 'audit_log'));
    }

    public function testApiTokensCreatedAndDeletedInTheUiAreLogged()
    {
        $model = new UsersManagerModel();
        $login = Piwik::getCurrentUserLogin();

        Piwik::postEvent('Controller.UsersManager.addNewToken', [[]]);
        $model->addTokenAuth($login, $model->generateRandomTokenAuth(), 'For the importer', Date::now()->getDatetime());
        Piwik::postEvent('Controller.UsersManager.addNewToken.end', ['', []]);
        $this->assertLastEntry('UsersManager', 'addNewToken', "User $login created an API token.");

        // Opening the form creates nothing.
        Piwik::postEvent('Controller.UsersManager.addNewToken', [[]]);
        Piwik::postEvent('Controller.UsersManager.addNewToken.end', ['', []]);
        $this->assertCount(1, $this->getEntries());

        $tokens = count($model->getAllNonSystemTokensForLogin($login));
        Piwik::postEvent('Controller.UsersManager.deleteToken', [[]]);
        $model->deleteAllTokensForUser($login);
        Piwik::postEvent('Controller.UsersManager.deleteToken.end', ['', []]);
        $this->assertLastEntry('UsersManager', 'deleteToken', "User $login deleted API tokens: $tokens.");
    }

    public function testScheduledReportsAreLogged()
    {
        $idReport = $this->call('ScheduledReports.addReport', [
            'idSite' => $this->idSite, 'description' => 'Weekly numbers', 'period' => 'week', 'hour' => 8, 'reportType' => 'email',
            'reportFormat' => 'pdf', 'reports' => ['VisitsSummary_get'], 'parameters' => ['emailMe' => true, 'displayFormat' => 1, 'additionalEmails' => ['boss@example.org']],
        ]);
        $this->assertLastEntry('ScheduledReports', 'addReport', "Added scheduled report Weekly numbers for site {$this->idSite}.");

        $this->call('ScheduledReports.deleteReport', ['idReport' => $idReport]);
        $this->assertLastEntry('ScheduledReports', 'deleteReport', "Deleted scheduled report with id $idReport.");
    }

    public function testOldEntriesAreDeletedWhenTheSettingSaysSoAndThatIsLogged()
    {
        $old = $this->auditLog->add('Goals', 'addGoal', 'anna', '', null, 'Old entry.', ['name' => 'Old']);
        $this->auditLog->add('Goals', 'addGoal', 'anna', '', null, 'New entry.', []);
        Db::query('UPDATE ' . Common::prefixTable(AuditLog::TABLE) . ' SET `timestamp` = ? WHERE id = ?', [Date::today()->subMonth(4)->getDatetime(), $old]);
        $tasks = new Tasks();

        $tasks->deleteOldEntries();
        $this->assertCount(2, $this->getEntries(), 'entries are kept forever by default');

        $settings = StaticContainer::get(SystemSettings::class);
        $settings->getSetting(SystemSettings::KEEP_MONTHS)->setValue(3);
        $tasks->deleteOldEntries();

        $messages = array_column($this->getEntries(), 'audit_log');
        $this->assertCount(2, $messages);
        $this->assertContains('New entry.', $messages);
        $this->assertStringContainsString('Deleted entries from before', $messages[0]);
        $this->assertSame('0', (string) Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable(AuditLog::DETAILS_TABLE) . ' WHERE base_id = ?', [$old]));
    }

    public function testAFailedApiCallIsNotLogged()
    {
        try {
            $this->call('SitesManager.updateSite', ['idSite' => 999, 'siteName' => 'Nowhere']);
            $this->fail('The website does not exist.');
        } catch (\Exception $e) {
            $this->assertStringNotContainsString('fail', $e->getMessage());
        }

        $this->assertSame([], $this->getEntries());
    }

    public function testLoginsAreLoggedForTheUserThatTried()
    {
        Piwik::postEvent('Login.authenticate.failed', ['mallory']);
        $entry = $this->assertLastEntry('Login', 'authenticate.failed', 'Login of user mallory failed.');
        $this->assertSame('mallory', $entry['user']);

        Piwik::postEvent('Login.authenticate.successful', ['anna']);
        $entry = $this->assertLastEntry('Login', 'authenticate.successful', 'User anna logged in successfully.');
        $this->assertSame('anna', $entry['user']);
    }

    /**
     * @dataProvider getEventsOfMatomo
     */
    public function testOtherEventsAreLogged(string $event, array $params, string $base, string $task, string $message)
    {
        Piwik::postEvent($event, $params);

        $this->assertLastEntry($base, $task, $message);
    }

    public function getEventsOfMatomo(): array
    {
        return [
            ['UsersManager.deleteUser', ['bo'], 'UsersManager', 'deleteUser', 'User bo deleted.'],
            ['UsersManager.updateUser.end', ['bo', true, 'bo@example.org', 'the-new-password'], 'UsersManager', 'updateUser.end', 'User bo changed. The password was changed.'],
            ['UsersManager.updateUser.end', ['bo', false, 'bo@example.org', ''], 'UsersManager', 'updateUser.end', 'User bo changed.'],
            ['UsersManager.inviteUser.accepted', ['bo', 'bo@example.org', 'anna'], 'UsersManager', 'inviteUser.accepted', 'User bo (bo@example.org) accepted the invitation.'],
            ['TwoFactorAuth.enabled', ['bo'], 'TwoFactorAuth', 'enabled', 'Two-factor authentication was turned on for user bo.'],
            ['TwoFactorAuth.disabled', ['bo'], 'TwoFactorAuth', 'disabled', 'Two-factor authentication was turned off for user bo.'],
            ['Login.resetPassword.initiated', ['bo'], 'Login', 'resetPassword.initiated', 'A password reset was requested for user bo.'],
            ['Login.resetPassword.confirmed', ['bo'], 'Login', 'resetPassword.confirmed', 'The password of user bo was reset.'],
            ['Login.logout', ['bo'], 'Login', 'logout', 'User bo logged out.'],
            ['PluginManager.pluginActivated', ['Goals'], 'PluginManager', 'pluginActivated', 'Plugin Goals activated.'],
            ['PluginManager.pluginDeactivated', ['Goals'], 'PluginManager', 'pluginDeactivated', 'Plugin Goals deactivated.'],
            ['PluginManager.pluginInstalled', ['Goals'], 'PluginManager', 'pluginInstalled', 'Plugin Goals installed.'],
            ['PluginManager.pluginUninstalled', ['Goals'], 'PluginManager', 'pluginUninstalled', 'Plugin Goals uninstalled.'],
            ['Core.configFileChanged', ['/config/config.ini.php'], 'Core', 'configFileChanged', 'Config file has changed at /config/config.ini.php.'],
            ['TagManager.containerFileChanged', ['container_a.js'], 'TagManager', 'containerFileChanged', 'Container file container_a.js changed.'],
            ['UsersManager.inviteUser.end', ['bo', 'bo@example.org'], 'UsersManager', 'inviteUser.end', 'User bo (bo@example.org) invited.'],
            ['BotTracker.insertBot.successful', [3, ['botName' => 'Crawler', 'botActive' => 1]], 'BotTracker', 'insertBot.successful', 'Bot Crawler added for site 3.'],
            ['UserFeedback.form.created', [3, 7, 'Page feedback'], 'UserFeedback', 'form.created', 'Added feedback form Page feedback (7) for site 3.'],
            ['UserFeedback.form.edit', [3, 7, 'Page feedback'], 'UserFeedback', 'form.edit', 'Updated feedback form Page feedback (7) for site 3.'],
            ['UserFeedback.form.copied', [3, 8, 'Page feedback copy', 7], 'UserFeedback', 'form.copied', 'Copied feedback form with id 7 to Page feedback copy (8) for site 3.'],
            ['UserFeedback.form.deleted', [3, 7, 'Page feedback'], 'UserFeedback', 'form.deleted', 'Deleted feedback form Page feedback (7) for site 3.'],
            ['UserFeedback.feedback.deleted', [3, [11, 12]], 'UserFeedback', 'feedback.deleted', 'Deleted feedback answers with id 11, 12 for site 3.'],
        ];
    }

    public function testSavedSystemSettingsAreLoggedWithTheirValues()
    {
        Piwik::postEvent('SystemSettings.updated', [new LoginSettings()]);

        $entry = $this->assertLastEntry('SystemSettings', 'updated', 'System settings of plugin Login updated.');
        $this->assertSame('Login', $entry['plugin']);
        $this->assertArrayHasKey('maxAllowedRetries', $entry);
    }

    public function testSecretsAreMaskedAlsoInsideStructuresAndEmptyValuesAreLeftOut()
    {
        (new EventLogger($this->auditLog))->log('Test', 'secrets', 'Saved.', [
            'name' => 'Mail',
            'smtp_password' => 'hunter2',
            'settings' => ['apiKey' => 'abc', 'region' => 'eu', 'nested' => ['token_auth' => 'xyz']],
            'empty' => '',
            'zero' => 0,
        ]);

        $entry = $this->getEntries()[0];
        $this->assertSame('Mail', $entry['name']);
        $this->assertSame(EventLogger::MASK, $entry['smtp_password']);
        $this->assertSame('{"apiKey":"***","region":"eu","nested":{"token_auth":"***"}}', $entry['settings']);
        $this->assertSame('0', $entry['zero']);
        $this->assertArrayNotHasKey('empty', $entry);
    }

    /**
     * @dataProvider getNames
     */
    public function testAValueIsMaskedWhenItsNameTellsItIsASecret(string $name, bool $isSecret)
    {
        (new EventLogger($this->auditLog))->log('Test', 'names', 'Saved.', [$name => 'value']);

        $this->assertSame($isSecret ? EventLogger::MASK : 'value', $this->getEntries()[0][$name]);
    }

    public function getNames(): array
    {
        return [
            ['password', true], ['passwordConfirmation', true], ['pwd', true], ['passphrase', true],
            ['token_auth', true], ['accessToken', true], ['client_secret', true], ['apiKey', true],
            ['licenseKey', true], ['key', true], ['privateKeys', true], ['authCode', true],
            ['Authorization', true], ['signature', true], ['sessionId', true], ['otp', true],
            ['name', false], ['idSite', false], ['keywords', false], ['author', false],
            ['userLogin', false], ['definition', false], ['access', false],
        ];
    }

    public function testASettingSentAsNameAndValueIsMaskedByItsName()
    {
        (new EventLogger($this->auditLog))->log('Test', 'settings', 'Saved.', [
            'settingValues' => ['Plugin' => [['name' => 'api_secret', 'value' => 'abc'], ['name' => 'region', 'value' => 'eu']]],
        ]);

        $this->assertSame(
            '{"Plugin":[{"name":"api_secret","value":"***"},{"name":"region","value":"eu"}]}',
            $this->getEntries()[0]['settingValues']
        );
    }

    public function testALoginThatIsTooLongToStoreIsStillLogged()
    {
        Piwik::postEvent('Login.authenticate.failed', [str_repeat('a', 500)]);

        $entry = $this->getEntries()[0];
        $this->assertSame('authenticate.failed', $entry['event_task']);
        $this->assertSame(str_repeat('a', 97) . '...', $entry['user']);
    }

    public function testAnEventThatCannotBeLoggedDoesNotStopWhatHappens()
    {
        foreach ((new EventLogger($this->auditLog))->getEventHandlers() as $handler) {
            $handler();
            $handler(new \stdClass(), 'not what the event passes', [[]]);
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @return mixed
     */
    private function call(string $method, array $params)
    {
        return Request::processRequest($method, $params, []);
    }

    private function getEntries(): array
    {
        return $this->auditLog->addDetails($this->auditLog->getEntries([], 0, 1000, true));
    }

    private function assertLastEntry(string $base, string $task, string $message): array
    {
        $entry = $this->getEntries()[0] ?? [];

        $this->assertSame([$base, $task, $message], [$entry['event_base'] ?? null, $entry['event_task'] ?? null, $entry['audit_log'] ?? null]);

        return $entry;
    }
}
