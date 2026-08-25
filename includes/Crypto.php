<?php

require_once __DIR__ . '/config.php';

function encryptSecret(?string $value): string
{
    $value = (string)$value;

    if ($value === '') {
        return '';
    }

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($value, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);

    return base64_encode($encrypted . '::' . $iv);
}

function decryptSecret(?string $value): string
{
    $value = (string)$value;

    if ($value === '') {
        return '';
    }

    $parts = explode('::', (string)base64_decode($value), 2);

    if (count($parts) !== 2) {
        return '';
    }

    [$encryptedData, $iv] = $parts;
    $decrypted = openssl_decrypt($encryptedData, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);

    return $decrypted !== false ? $decrypted : '';
}
