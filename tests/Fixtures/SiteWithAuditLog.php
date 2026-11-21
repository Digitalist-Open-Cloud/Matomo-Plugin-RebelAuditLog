<?php

namespace Piwik\Plugins\RebelAuditLog\tests\Fixtures;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\RebelAuditLog\AuditLog;
use Piwik\Tests\Framework\Fixture;

/**
 * A website and an audit log with 60 entries of the user "anna" on one day and one of "bo".
 */
class SiteWithAuditLog extends Fixture
{
    public const DAY = '2024-03-05';

    public function setUp(): void
    {
        if (!self::siteCreated(1)) {
            self::createWebsite('2024-01-01 00:00:00');
        }

        Db::query('TRUNCATE ' . Common::prefixTable(AuditLog::TABLE));
        Db::query('TRUNCATE ' . Common::prefixTable(AuditLog::DETAILS_TABLE));

        $auditLog = new AuditLog();
        for ($number = 1; $number <= 60; $number++) {
            $auditLog->add('Goals', 'addGoal', 'anna', '10.0.0.1', null, "Added new Goal Goal $number for site 1.", ['name' => "Goal $number"]);
        }
        $auditLog->add('UsersManager', 'setSuperUserAccess', 'bo', '10.0.0.2', null, 'carl was added as super user.', ['userLogin' => 'carl']);

        Db::query('UPDATE ' . Common::prefixTable(AuditLog::TABLE) . ' SET `timestamp` = ?', [self::DAY . ' 12:00:00']);
    }

    public function tearDown(): void
    {
    }
}
