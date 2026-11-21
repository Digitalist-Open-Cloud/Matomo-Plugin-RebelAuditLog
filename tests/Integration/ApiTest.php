<?php

namespace Piwik\Plugins\RebelAuditLog\tests\Integration;

use Piwik\Common;
use Piwik\Db;
use Piwik\NoAccessException;
use Piwik\Plugins\RebelAuditLog\API;
use Piwik\Plugins\RebelAuditLog\AuditLog;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group RebelAuditLog
 * @group Plugins
 */
class ApiTest extends IntegrationTestCase
{
    private API $api;

    public function setUp(): void
    {
        parent::setUp();

        FakeAccess::clearAccess(true);
        Fixture::createWebsite('2024-01-01 00:00:00');
        $this->api = API::getInstance();

        // Setting up Matomo and the website is logged too.
        Db::query('TRUNCATE ' . Common::prefixTable(AuditLog::TABLE));
        Db::query('TRUNCATE ' . Common::prefixTable(AuditLog::DETAILS_TABLE));

        $auditLog = new AuditLog();
        $ids = [
            $auditLog->add('UsersManager', 'addUser.end', 'anna', '10.0.0.1', null, 'User bo added.', ['userLogin' => 'bo']),
            $auditLog->add('Goals', 'addGoal', 'bo', '10.0.0.2', null, 'Added new Goal Signup.', ['name' => 'Signup', 'user' => 'not the user']),
            $auditLog->add('PluginManager', 'pluginActivated', AuditLog::CONSOLE_USER, '', null, 'Plugin Goals activated.', []),
        ];
        // The entries get the time of the database, so each is put on a day of its own.
        foreach (['2024-03-04 23:59:59', '2024-03-05 12:00:00', '2024-03-06 00:00:00'] as $index => $time) {
            Db::query('UPDATE ' . Common::prefixTable(AuditLog::TABLE) . ' SET `timestamp` = ? WHERE id = ?', [$time, $ids[$index]]);
        }
    }

    public function provideContainerConfig()
    {
        return ['Piwik\Access' => new FakeAccess()];
    }

    public function testEntriesComeOldestFirstUnlessAskedOtherwise()
    {
        $this->assertSame(['User bo added.', 'Added new Goal Signup.', 'Plugin Goals activated.'], $this->getMessages());
        $this->assertSame(['Plugin Goals activated.', 'Added new Goal Signup.', 'User bo added.'], $this->getMessages(['order' => 'desc']));
        $this->assertSame(['Added new Goal Signup.'], $this->getMessages(['offset' => 1, 'limit' => 1]));
    }

    public function testAnOrderThatIsNotOneOfTheTwoIsNotPartOfTheQuery()
    {
        $this->assertCount(3, $this->getMessages(['order' => 'ASC; DROP TABLE ' . Common::prefixTable(AuditLog::TABLE)]));
        $this->assertCount(3, $this->getMessages());
    }

    public function testEntriesCanBeFilteredByUserEventAndDay()
    {
        $this->assertSame(['Added new Goal Signup.'], $this->getMessages(['selectedUser' => 'bo']));
        $this->assertSame(['User bo added.'], $this->getMessages(['selectedEventBase' => 'UsersManager']));
        $this->assertSame(['User bo added.', 'Added new Goal Signup.'], $this->getMessages(['excludeConsole' => true]));
        $this->assertSame(['Added new Goal Signup.'], $this->getMessages(['startDate' => '2024-03-05', 'endDate' => '2024-03-05']));
        $this->assertSame(['Added new Goal Signup.', 'Plugin Goals activated.'], $this->getMessages(['startDate' => '2024-03-05']));
        $this->assertSame([], $this->getMessages(['selectedUser' => "bo' OR '1'='1"]));
    }

    public function testEveryEntryHasTheDetailsOfAllAsColumns()
    {
        $entries = $this->api->getAudits();

        $this->assertSame(['bo', '', ''], array_column($entries, 'userLogin'));
        $this->assertSame(['', 'Signup', ''], array_column($entries, 'name'));
        // A detail cannot replace who did it.
        $this->assertSame(['anna', 'bo', AuditLog::CONSOLE_USER], array_column($entries, 'user'));
        $this->assertSame(['', 'not the user', ''], array_column($entries, 'detail_user'));
    }

    public function testOnlyASuperUserCanReadTheLog()
    {
        FakeAccess::clearAccess(false, [1]);

        $this->expectException(NoAccessException::class);
        $this->api->getAudits();
    }

    public function testNothingCanBeWrittenToTheLogThroughTheApi()
    {
        $methods = array_map(
            fn (\ReflectionMethod $method) => $method->getName(),
            (new \ReflectionClass(API::class))->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        $this->assertSame(['getAudits'], array_values(array_filter($methods, fn (string $name) => strpos($name, 'get') === 0 && $name !== 'getInstance')));
        $this->assertNotContains('logAudit', $methods);
    }

    /**
     * @return string[]
     */
    private function getMessages(array $params = []): array
    {
        $params += ['offset' => 0, 'limit' => 50000, 'excludeConsole' => false, 'selectedUser' => '', 'selectedEventBase' => '', 'order' => 'ASC', 'startDate' => '', 'endDate' => ''];

        return array_column($this->api->getAudits(
            $params['offset'],
            $params['limit'],
            $params['excludeConsole'],
            $params['selectedUser'],
            $params['selectedEventBase'],
            $params['order'],
            $params['startDate'],
            $params['endDate']
        ), 'audit_log');
    }
}
