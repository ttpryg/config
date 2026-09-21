<?php

declare(strict_types=1);

namespace Ttpryg\Config\Test;

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
        $config = new ConfigRepository;
        $config->set('database.connections.mysql.host', '127.0.0.1');
        $config->set('database.connections.mysql.port', 3306);

        $this->assertEquals('127.0.0.1', $config->get('database.connections.mysql.host'));
        $this->assertEquals(3306, $config->get('database.connections.mysql.port'));
        $this->assertEquals('default', $config->get('database.connections.mysql.unknown', 'default'));
    }

    public function test_has_and_forget(): void
    {
        $config = new ConfigRepository(['app' => ['name' => 'Slim', 'debug' => true]]);

        $this->assertTrue($config->has('app.name'));
        $this->assertTrue($config->has('app.debug'));
        $this->assertFalse($config->has('app.version'));

        $config->forget('app.debug');
        $this->assertFalse($config->has('app.debug'));
    }

    public function test_array_access(): void
    {
        $config = new ConfigRepository;
        $config['jwt.secret'] = 'supersecret';

        $this->assertTrue(isset($config['jwt.secret']));
        $this->assertEquals('supersecret', $config['jwt.secret']);

        unset($config['jwt.secret']);
        $this->assertFalse(isset($config['jwt.secret']));
    }

    public function test_create_from_array(): void
    {
        $config = ConfigManager::createFromArray(['app_name' => 'TestApp']);
        $this->assertEquals('TestApp', $config->get('app_name'));
    }

    public function test_database_driver_save_and_load(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $driver = new PdoDatabaseDriver($pdo, 'app_configs');

        $config = new ConfigRepository([
            'site' => [
                'name' => 'My Awesome Site',
                'maintenance' => false,
            ],
            'jwt_ttl' => 3600,
        ]);

        // Persist configs to SQLite database table
        $config->saveToDatabase($driver);

        // Load configs into a fresh repository instance from database driver
        $loadedConfig = ConfigManager::createFromDatabase($driver);

        $this->assertEquals('My Awesome Site', $loadedConfig->get('site.name'));
        $this->assertFalse($loadedConfig->get('site.maintenance'));
        $this->assertEquals(3600, $loadedConfig->get('jwt_ttl'));
    }

    public function test_database_driver_save_and_load_with_encryption(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $driver = new PdoDatabaseDriver($pdo, 'app_configs');
        $encryptor = new ConfigEncryptor('app-secret-key');

        $config = new ConfigRepository([
            'site' => [
                'name' => 'My Awesome Site',
                'maintenance' => false,
            ],
            'jwt_ttl' => 3600,
        ], $encryptor);

        $config->saveToDatabase($driver);

        $storedRows = $driver->all();
        $this->assertStringStartsWith(ConfigEncryptor::MARKER, $storedRows['site']);
        $this->assertNotSame('{"name":"My Awesome Site","maintenance":false}', $storedRows['site']);

        $loadedConfig = ConfigManager::createFromDatabase($driver, $encryptor);

        $this->assertEquals('My Awesome Site', $loadedConfig->get('site.name'));
        $this->assertFalse($loadedConfig->get('site.maintenance'));
        $this->assertEquals(3600, $loadedConfig->get('jwt_ttl'));
    }

    public function test_database_load_legacy_plain_values_still_work(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $driver = new PdoDatabaseDriver($pdo, 'app_configs');
        $driver->set('legacy_key', 'legacy-value');

        $loadedConfig = ConfigManager::createFromDatabase($driver, new ConfigEncryptor('app-secret-key'));

        $this->assertEquals('legacy-value', $loadedConfig->get('legacy_key'));
    }
}
