<?php

declare(strict_types=1);

namespace Ttpryg\Config\Encryption;

use RuntimeException;

class ConfigEncryptor
{
    private const CIPHER = 'aes-256-gcm';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public const MARKER = 'enc:v1:';

    public function __construct(private readonly string $key)
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('The openssl extension is required for config encryption.');
        }
    }

    public function encrypt(string $plaintext): string
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->deriveKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt config value.');
        }

        return self::MARKER.base64_encode($iv.$tag.$ciphertext);
    }

    public function decrypt(string $payload): string
    {
        if (! $this->isEncrypted($payload)) {
            throw new RuntimeException('Invalid encrypted payload.');
        }

        $raw = base64_decode(substr($payload, strlen(self::MARKER)), true);

        if ($raw === false || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Invalid encrypted payload.');
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->deriveKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Failed to decrypt config value.');
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::MARKER);
    }

    private function deriveKey(): string
    {
        return hash('sha256', $this->key, true);
    }
}
