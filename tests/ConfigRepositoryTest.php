<?php

declare(strict_types=1);

namespace Ttpryg\Config\Test;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\Config\ConfigManager;
use Ttpryg\Config\ConfigRepository;
use Ttpryg\Config\Drivers\PdoDatabaseDriver;
use Ttpryg\Config\Encryption\ConfigEncryptor;

class ConfigRepositoryTest extends TestCase
{
    public function test_get_and_set_dot_notation(): void
    {
        $configRepository = new ConfigRepository;
        $configRepository->set('database.connections.mysql.host', '127.0.0.1');
        $configRepository->set('database.connections.mysql.port', 3306);

        $this->assertEquals('127.0.0.1', $configRepository->get('database.connections.mysql.host'));
        $this->assertEquals(3306, $configRepository->get('database.connections.mysql.port'));
        $this->assertEquals('default', $configRepository->get('database.connections.mysql.unknown', 'default'));
    }

    public function test_has_and_forget(): void
    {
        $configRepository = new ConfigRepository(['app' => ['name' => 'Slim', 'debug' => true]]);

        $this->assertTrue($configRepository->has('app.name'));
        $this->assertTrue($configRepository->has('app.debug'));
        $this->assertFalse($configRepository->has('app.version'));

        $configRepository->forget('app.debug');
        $this->assertFalse($configRepository->has('app.debug'));
    }

    public function test_array_access(): void
    {
        $configRepository = new ConfigRepository;
        $configRepository['jwt.secret'] = 'supersecret';

        $this->assertTrue(isset($configRepository['jwt.secret']));
        $this->assertEquals('supersecret', $configRepository['jwt.secret']);

        unset($configRepository['jwt.secret']);
        $this->assertFalse(isset($configRepository['jwt.secret']));
    }

    public function test_create_from_array(): void
    {
        $configRepository = ConfigManager::createFromArray(['app_name' => 'TestApp']);
        $this->assertEquals('TestApp', $configRepository->get('app_name'));
    }

    public function test_database_driver_save_and_load(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdoDatabaseDriver = new PdoDatabaseDriver($pdo, 'app_configs');

        $configRepository = new ConfigRepository([
            'site' => [
                'name' => 'My Awesome Site',
                'maintenance' => false,
            ],
            'jwt_ttl' => 3600,
        ]);

        // Persist configs to SQLite database table
        $configRepository->saveToDatabase($pdoDatabaseDriver);

        // Load configs into a fresh repository instance from database driver
        $loadedConfig = ConfigManager::createFromDatabase($pdoDatabaseDriver);

        $this->assertEquals('My Awesome Site', $loadedConfig->get('site.name'));
        $this->assertFalse($loadedConfig->get('site.maintenance'));
        $this->assertEquals(3600, $loadedConfig->get('jwt_ttl'));
    }

    public function test_database_driver_save_and_load_with_encryption(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdoDatabaseDriver = new PdoDatabaseDriver($pdo, 'app_configs');
        $configEncryptor = new ConfigEncryptor('app-secret-key');

        $configRepository = new ConfigRepository([
            'site' => [
                'name' => 'My Awesome Site',
                'maintenance' => false,
            ],
            'jwt_ttl' => 3600,
        ], $configEncryptor);

        $configRepository->saveToDatabase($pdoDatabaseDriver);

        $storedRows = $pdoDatabaseDriver->all();
        $this->assertStringStartsWith(ConfigEncryptor::MARKER, $storedRows['site']);
        $this->assertNotSame('{"name":"My Awesome Site","maintenance":false}', $storedRows['site']);

        $loadedConfig = ConfigManager::createFromDatabase($pdoDatabaseDriver, $configEncryptor);

        $this->assertEquals('My Awesome Site', $loadedConfig->get('site.name'));
        $this->assertFalse($loadedConfig->get('site.maintenance'));
        $this->assertEquals(3600, $loadedConfig->get('jwt_ttl'));
    }

    public function test_database_load_legacy_plain_values_still_work(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdoDatabaseDriver = new PdoDatabaseDriver($pdo, 'app_configs');
        $pdoDatabaseDriver->set('legacy_key', 'legacy-value');

        $configRepository = ConfigManager::createFromDatabase($pdoDatabaseDriver, new ConfigEncryptor('app-secret-key'));

        $this->assertEquals('legacy-value', $configRepository->get('legacy_key'));
    }

    public function test_database_driver_save_and_load_scalar_types(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdoDatabaseDriver = new PdoDatabaseDriver($pdo, 'app_configs');

        $configRepository = new ConfigRepository([
            'bool_false' => false,
            'bool_true' => true,
            'null_val' => null,
            'int_num' => 42,
            'float_num' => 3.14,
            'string_val' => 'hello world',
            'numeric_string' => '12345',
        ]);

        $configRepository->saveToDatabase($pdoDatabaseDriver);

        $loadedConfig = ConfigManager::createFromDatabase($pdoDatabaseDriver);

        $this->assertFalse($loadedConfig->get('bool_false'));
        $this->assertTrue($loadedConfig->get('bool_true'));
        $this->assertNull($loadedConfig->get('null_val'));
        $this->assertSame(42, $loadedConfig->get('int_num'));
        $this->assertSame(3.14, $loadedConfig->get('float_num'));
        $this->assertSame('hello world', $loadedConfig->get('string_val'));
        $this->assertSame('12345', $loadedConfig->get('numeric_string'));

        // Test saving single scalar key
        $configRepository->set('single_bool_false', false);
        $configRepository->saveToDatabase($pdoDatabaseDriver, 'single_bool_false');

        $reloaded = ConfigManager::createFromDatabase($pdoDatabaseDriver);
        $this->assertFalse($reloaded->get('single_bool_false'));
    }

    public function test_database_driver_atomic_upsert_and_forget(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdoDatabaseDriver = new PdoDatabaseDriver($pdo, 'app_configs');

        // Initial set
        $pdoDatabaseDriver->set('feature_flag', 'enabled');
        $this->assertSame('enabled', $pdoDatabaseDriver->get('feature_flag'));

        // Upsert existing key
        $pdoDatabaseDriver->set('feature_flag', 'disabled');
        $this->assertSame('disabled', $pdoDatabaseDriver->get('feature_flag'));

        // Forget key
        $pdoDatabaseDriver->forget('feature_flag');
        $this->assertNull($pdoDatabaseDriver->get('feature_flag'));
    }

    public function test_database_driver_auto_create_table_option(): void
    {
        $pdo = new PDO('sqlite::memory:');
        // When autoCreateTable is false, table should not exist yet
        $pdoDatabaseDriver = new PdoDatabaseDriver($pdo, 'custom_configs', autoCreateTable: false);

        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='custom_configs'");
        $this->assertFalse($stmt->fetchColumn());

        // Manually ensure table exists
        $pdoDatabaseDriver->ensureTableExists();
        $stmt2 = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='custom_configs'");
        $this->assertSame('custom_configs', $stmt2->fetchColumn());
    }

    public function test_database_driver_invalid_identifier_throws(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $this->expectException(InvalidArgumentException::class);
        new PdoDatabaseDriver($pdo, 'configs; DROP TABLE users; --');
    }

    public function test_load_file_and_load_dir(): void
    {
        $tempDir = sys_get_temp_dir().'/ttpryg_config_test_'.uniqid();
        mkdir($tempDir);

        file_put_contents($tempDir.'/app.php', "<?php return ['name' => 'MyApp', 'nested' => ['foo' => 'bar', 'debug' => true]];");
        file_put_contents($tempDir.'/db.php', "<?php return ['default' => 'mysql'];");

        $configRepository = ConfigManager::createFromDirectory($tempDir);

        $this->assertSame('MyApp', $configRepository->get('app.name'));
        $this->assertSame('bar', $configRepository->get('app.nested.foo'));
        $this->assertTrue($configRepository->get('app.nested.debug'));
        $this->assertSame('mysql', $configRepository->get('db.default'));

        // Test recursive replacement merge
        $overrideFile = $tempDir.'/override_app.php';
        file_put_contents($overrideFile, "<?php return ['nested' => ['foo' => 'updated']];");

        // Manually load into app key
        $configRepository->set('app', array_replace_recursive($configRepository->get('app'), require $overrideFile));
        $this->assertSame('updated', $configRepository->get('app.nested.foo'));
        $this->assertTrue($configRepository->get('app.nested.debug')); // Unaffected nested key preserved!

        // Cleanup
        unlink($tempDir.'/app.php');
        unlink($tempDir.'/db.php');
        unlink($overrideFile);
        rmdir($tempDir);
    }

    public function test_load_file_not_found_throws(): void
    {
        $configRepository = new ConfigRepository;

        $this->expectException(InvalidArgumentException::class);
        $configRepository->loadFile('/non/existent/path/config.php');
    }

    public function test_load_file_invalid_return_throws(): void
    {
        $tempFile = sys_get_temp_dir().'/invalid_config_'.uniqid().'.php';
        file_put_contents($tempFile, "<?php return 'not an array';");

        $configRepository = new ConfigRepository;

        try {
            $this->expectException(InvalidArgumentException::class);
            $configRepository->loadFile($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_load_dir_not_found_throws(): void
    {
        $configRepository = new ConfigRepository;

        $this->expectException(InvalidArgumentException::class);
        $configRepository->loadDir('/non/existent/directory/path');
    }
}
