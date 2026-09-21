<?php

declare(strict_types=1);

namespace Ttpryg\Config;

use Ttpryg\Config\Drivers\DatabaseDriverInterface;

interface ConfigInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function forget(string $key): void;

    public function all(): array;

    public function loadFile(string $filePath): void;

    public function loadDir(string $directoryPath): void;

    public function loadDatabase(DatabaseDriverInterface $databaseDriver): void;

    public function saveToDatabase(DatabaseDriverInterface $databaseDriver, ?string $key = null): void;
}
