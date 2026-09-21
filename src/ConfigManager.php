<?php

declare(strict_types=1);

namespace Ttpryg\Config;

use Ttpryg\Config\Drivers\DatabaseDriverInterface;
use Ttpryg\Config\Encryption\ConfigEncryptor;

class ConfigManager
{
    /**
     * Create a ConfigRepository instance pre-loaded with files from a directory.
     */
    public static function createFromDirectory(string $directoryPath): ConfigRepository
    {
        $configRepository = new ConfigRepository;
        $configRepository->loadDir($directoryPath);

        return $configRepository;
    }

    /**
     * Create a ConfigRepository instance pre-loaded from a database driver.
     */
    public static function createFromDatabase(DatabaseDriverInterface $databaseDriver, ?ConfigEncryptor $configEncryptor = null): ConfigRepository
    {
        $configRepository = new ConfigRepository([], $configEncryptor);
        $configRepository->loadDatabase($databaseDriver);

        return $configRepository;
    }

    /**
     * Create a ConfigRepository instance from an array of configuration values.
     */
    public static function createFromArray(array $items): ConfigRepository
    {
        return new ConfigRepository($items);
    }
}
