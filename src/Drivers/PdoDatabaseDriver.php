<?php

declare(strict_types=1);

namespace Ttpryg\Config\Drivers;

use InvalidArgumentException;
use PDO;

class PdoDatabaseDriver implements DatabaseDriverInterface
{
    public function __construct(
        protected PDO $pdo,
        protected string $table = 'configs',
        protected string $keyColumn = 'key',
        protected string $valueColumn = 'value',
        bool $autoCreateTable = true
    ) {
        $this->validateIdentifier($this->table);
        $this->validateIdentifier($this->keyColumn);
        $this->validateIdentifier($this->valueColumn);

        if ($autoCreateTable) {
            $this->ensureTableExists();
        }
    }

    public function get(string $key): ?string
    {
        $table = $this->quoteIdentifier($this->table);
        $keyCol = $this->quoteIdentifier($this->keyColumn);
        $valCol = $this->quoteIdentifier($this->valueColumn);

        $stmt = $this->pdo->prepare("SELECT {$valCol} FROM {$table} WHERE {$keyCol} = :key LIMIT 1");
        $stmt->execute(['key' => $key]);

        $result = $stmt->fetchColumn();

        return $result !== false ? (string) $result : null;
    }

    public function set(string $key, string $value): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $table = $this->quoteIdentifier($this->table);
        $keyCol = $this->quoteIdentifier($this->keyColumn);
        $valCol = $this->quoteIdentifier($this->valueColumn);

        if ($driver === 'mysql') {
            $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$keyCol}, {$valCol}) VALUES (:key, :value) ON DUPLICATE KEY UPDATE {$valCol} = :value_update");
            $stmt->execute(['key' => $key, 'value' => $value, 'value_update' => $value]);

            return;
        }

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$keyCol}, {$valCol}) VALUES (:key, :value) ON CONFLICT ({$keyCol}) DO UPDATE SET {$valCol} = EXCLUDED.{$valCol}");
            $stmt->execute(['key' => $key, 'value' => $value]);

            return;
        }

        $existing = $this->get($key);

        if ($existing !== null) {
            $stmt = $this->pdo->prepare("UPDATE {$table} SET {$valCol} = :value WHERE {$keyCol} = :key");
            $stmt->execute(['value' => $value, 'key' => $key]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$keyCol}, {$valCol}) VALUES (:key, :value)");
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
    }

    public function forget(string $key): void
    {
        $table = $this->quoteIdentifier($this->table);
        $keyCol = $this->quoteIdentifier($this->keyColumn);

        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE {$keyCol} = :key");
        $stmt->execute(['key' => $key]);
    }

    public function all(): array
    {
        $table = $this->quoteIdentifier($this->table);
        $keyCol = $this->quoteIdentifier($this->keyColumn);
        $valCol = $this->quoteIdentifier($this->valueColumn);

        $stmt = $this->pdo->query("SELECT {$keyCol}, {$valCol} FROM {$table}");
        if (! $stmt) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return is_array($rows) ? $rows : [];
    }

    public function ensureTableExists(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $table = $this->quoteIdentifier($this->table);
        $keyCol = $this->quoteIdentifier($this->keyColumn);
        $valCol = $this->quoteIdentifier($this->valueColumn);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                {$keyCol} VARCHAR(255) PRIMARY KEY,
                {$valCol} TEXT
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                {$keyCol} VARCHAR(255) PRIMARY KEY,
                {$valCol} LONGTEXT
            )";
        }

        $this->pdo->exec($sql);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'mysql' => "`{$identifier}`",
            default => "\"{$identifier}\"",
        };
    }

    private function validateIdentifier(string $identifier): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException("Invalid SQL identifier: {$identifier}");
        }
    }
}
