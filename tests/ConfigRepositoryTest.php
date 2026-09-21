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
}
