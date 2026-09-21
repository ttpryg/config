<?php

declare(strict_types=1);

namespace Ttpryg\Config;

use ArrayAccess;
use InvalidArgumentException;
use Ttpryg\Config\Drivers\DatabaseDriverInterface;
use Ttpryg\Config\Encryption\ConfigEncryptor;

class ConfigRepository implements ArrayAccess, ConfigInterface
{
    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(
        protected array $items = [],
        protected ?ConfigEncryptor $encryptor = null
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->items;
        }

        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $array = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);

        $array = &$this->items;
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (! isset($array[$segment]) || ! is_array($array[$segment])) {
                $array[$segment] = [];
            }

            $array = &$array[$segment];
        }

        $array[array_shift($keys)] = $value;
    }

    public function has(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        if (array_key_exists($key, $this->items)) {
            return true;
        }

        $array = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    public function forget(string $key): void
    {
        $keys = explode('.', $key);
        $array = &$this->items;

        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (! isset($array[$segment]) || ! is_array($array[$segment])) {
                return;
            }

            $array = &$array[$segment];
        }

        unset($array[array_shift($keys)]);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function loadFile(string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Config file not found: {$filePath}");
        }

        $configData = require $filePath;
        if (! is_array($configData)) {
            throw new InvalidArgumentException("Config file must return an array: {$filePath}");
        }

        $key = pathinfo($filePath, PATHINFO_FILENAME);
        $this->set($key, array_merge((array) $this->get($key, []), $configData));
    }

    public function loadDir(string $directoryPath): void
    {
        if (! is_dir($directoryPath)) {
            throw new InvalidArgumentException("Config directory not found: {$directoryPath}");
        }

        $files = glob(rtrim($directoryPath, '/\\').'/*.php') ?: [];
        foreach ($files as $file) {
            $this->loadFile($file);
        }
    }

    public function loadDatabase(DatabaseDriverInterface $databaseDriver): void
    {
        $rows = $databaseDriver->all();
        foreach ($rows as $key => $rawValue) {
            if ($this->encryptor instanceof ConfigEncryptor && $this->encryptor->isEncrypted($rawValue)) {
                $rawValue = $this->encryptor->decrypt($rawValue);
            }

            $decodedValue = $this->decodeValue($rawValue);
            $this->set($key, $decodedValue);
        }
    }

    public function saveToDatabase(DatabaseDriverInterface $databaseDriver, ?string $key = null): void
    {
        if ($key !== null) {
            $value = $this->get($key);
            $encodedValue = $this->encodeValue($value);
            $databaseDriver->set($key, $encodedValue);

            return;
        }

        foreach ($this->items as $itemKey => $value) {
            $encodedValue = $this->encodeValue($value);
            $databaseDriver->set($itemKey, $encodedValue);
        }
    }

    // ArrayAccess implementation

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }

    private function encodeValue(mixed $value): string
    {
        $encodedValue = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        if ($this->encryptor instanceof ConfigEncryptor) {
            return $this->encryptor->encrypt($encodedValue);
        }

        return $encodedValue;
    }

    private function decodeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }
}
