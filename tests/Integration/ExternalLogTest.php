<?php

namespace Piwik\Plugins\RebelAuditLog\tests\Integration;

use PDO;
use Piwik\Config;
use Piwik\Plugins\RebelAuditLog\AuditLog;
use Piwik\Plugins\RebelAuditLog\ExternalLog;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * The MariaDB/MySQL part runs against the test database, as if it were external. The
 * PostgreSQL part needs the environment variables REBELAUDITLOG_PGSQL_DSN,
 * REBELAUDITLOG_PGSQL_USER and REBELAUDITLOG_PGSQL_PASSWORD.
 *
 * @group RebelAuditLog
 * @group Plugins
 */
class ExternalLogTest extends IntegrationTestCase
{
    private const PREFIX = 'ext_';

    public function tearDown(): void
    {
        ExternalLog::disconnect();
        Config::getInstance()->RebelAuditLog = [];
        parent::tearDown();
    }

    /**
     * @dataProvider getDatabases
     */
    public function testAnEntryIsAlsoWrittenToTheExternalDatabase(string $kind)
    {
        $connection = $this->configure($kind);
        (new ExternalLog())->createTables();
        // Creating them again changes nothing.
        (new ExternalLog())->createTables();

        (new AuditLog())->add('Goals', 'addGoal', 'anna', '10.0.0.1', null, 'Added new Goal Signup.', ['name' => 'Signup', 'idSite' => '1']);

        $quote = $kind === 'pgsql' ? '"' : '`';
        $entry = $connection->query('SELECT * FROM ' . $quote . self::PREFIX . AuditLog::TABLE . $quote)->fetchAll(PDO::FETCH_ASSOC);
        $details = $connection->query('SELECT * FROM ' . $quote . self::PREFIX . AuditLog::DETAILS_TABLE . $quote . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $entry);
        $this->assertSame(
            ['matomo.test', 'Goals', 'addGoal', 'anna', '10.0.0.1', 'Added new Goal Signup.'],
            [$entry[0]['source'], $entry[0]['event_base'], $entry[0]['event_task'], $entry[0]['user'], $entry[0]['ip'], $entry[0]['audit_log']]
        );
        $this->assertNotEmpty($entry[0]['timestamp']);
        $this->assertSame(['name' => 'Signup', 'idSite' => '1'], array_column($details, 'value', 'key'));
        $this->assertSame([(string) $entry[0]['id']], array_values(array_unique(array_map('strval', array_column($details, 'base_id')))));
        $this->assertSame(1, (new ExternalLog())->count());
    }

    public function getDatabases(): array
    {
        return [['mysql'], ['pgsql']];
    }

    public function testAnEntryIsStillLoggedInMatomoWhenTheExternalDatabaseCannotBeReached()
    {
        Config::getInstance()->RebelAuditLog = ['external_dsn' => 'mysql:host=127.0.0.1;port=1;dbname=nowhere', 'external_username' => 'nobody'];
        $auditLog = new AuditLog();
        $before = $auditLog->count([]);

        $auditLog->add('Goals', 'addGoal', 'anna', '', null, 'Added new Goal Signup.', []);

        $this->assertSame($before + 1, $auditLog->count([]));
    }

    public function testWithoutAnExternalDatabaseNothingIsConfigured()
    {
        $this->assertFalse((new ExternalLog())->isConfigured());
    }

    public function testOnlyTheSupportedKindsOfDatabaseAreAccepted()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('"mysql:" or "pgsql:"');

        (new ExternalLog(['external_dsn' => 'sqlite::memory:']))->createTables();
    }

    public function testTheTablePrefixCannotCarrySql()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('letters, digits and underscores');

        (new ExternalLog(['external_dsn' => 'mysql:host=db', 'external_table_prefix' => 'x`; DROP TABLE y; --']))->getTableDefinitions();
    }

    private function configure(string $kind): PDO
    {
        if ($kind === 'pgsql') {
            $dsn = (string) getenv('REBELAUDITLOG_PGSQL_DSN');
            if ($dsn === '' || !extension_loaded('pdo_pgsql')) {
                $this->markTestSkipped('No PostgreSQL database to test with: REBELAUDITLOG_PGSQL_DSN is not set.');
            }
            $user = (string) getenv('REBELAUDITLOG_PGSQL_USER');
            $password = (string) getenv('REBELAUDITLOG_PGSQL_PASSWORD');
        } else {
            $database = Config::getInstance()->database;
            $dsn = "mysql:host={$database['host']};port=" . ($database['port'] ?: 3306) . ";dbname={$database['dbname']}";
            $user = $database['username'];
            $password = $database['password'];
        }

        Config::getInstance()->RebelAuditLog = [
            'external_dsn' => $dsn,
            'external_username' => $user,
            'external_password' => $password,
            'external_table_prefix' => self::PREFIX,
            'external_source' => 'matomo.test',
        ];

        $connection = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $quote = $kind === 'pgsql' ? '"' : '`';
        foreach ([AuditLog::TABLE, AuditLog::DETAILS_TABLE] as $table) {
            $connection->exec('DROP TABLE IF EXISTS ' . $quote . self::PREFIX . $table . $quote);
        }

        return $connection;
    }
}
