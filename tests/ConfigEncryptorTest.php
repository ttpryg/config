<?php

declare(strict_types=1);

namespace Ttpryg\Config\Test;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ttpryg\Config\Encryption\ConfigEncryptor;

class ConfigEncryptorTest extends TestCase
{
    public function test_encrypt_and_decrypt_round_trip(): void
    {
        $encryptor = new ConfigEncryptor('my-secret-key');

        $ciphertext = $encryptor->encrypt('My Awesome Site');

        $this->assertNotSame('My Awesome Site', $ciphertext);
        $this->assertTrue($encryptor->isEncrypted($ciphertext));
        $this->assertStringStartsWith(ConfigEncryptor::MARKER, $ciphertext);
        $this->assertSame('My Awesome Site', $encryptor->decrypt($ciphertext));
    }

    public function test_decrypt_with_wrong_key_throws(): void
    {
        $encryptor = new ConfigEncryptor('secret-a');
        $ciphertext = $encryptor->encrypt('secrets');

        $this->expectException(RuntimeException::class);

        (new ConfigEncryptor('secret-b'))->decrypt($ciphertext);
    }

    public function test_decrypt_invalid_payload_throws(): void
    {
        $this->expectException(RuntimeException::class);

        (new ConfigEncryptor('secret'))->decrypt('plain-value');
    }

    public function test_is_encrypted_detects_plain_value(): void
    {
        $encryptor = new ConfigEncryptor('secret');

        $this->assertFalse($encryptor->isEncrypted('plain-value'));
        $this->assertTrue($encryptor->isEncrypted(ConfigEncryptor::MARKER.'abc'));
    }
}
