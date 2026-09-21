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
        $configEncryptor = new ConfigEncryptor('my-secret-key');

        $ciphertext = $configEncryptor->encrypt('My Awesome Site');

        $this->assertNotSame('My Awesome Site', $ciphertext);
        $this->assertTrue($configEncryptor->isEncrypted($ciphertext));
        $this->assertStringStartsWith(ConfigEncryptor::MARKER, $ciphertext);
        $this->assertSame('My Awesome Site', $configEncryptor->decrypt($ciphertext));
    }

    public function test_decrypt_with_wrong_key_throws(): void
    {
        $configEncryptor = new ConfigEncryptor('secret-a');
        $ciphertext = $configEncryptor->encrypt('secrets');

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
        $configEncryptor = new ConfigEncryptor('secret');

        $this->assertFalse($configEncryptor->isEncrypted('plain-value'));
        $this->assertTrue($configEncryptor->isEncrypted(ConfigEncryptor::MARKER.'abc'));
    }
}
