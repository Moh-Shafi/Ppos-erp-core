<?php

namespace App\Contracts;

interface ProviderAdapterInterface
{
    public static function slug(): string;

    public function healthCheck(array $credentials, array $config): array;

    public function execute(string $method, string $path, array $data, array $credentials, array $config, ?string $idempotencyKey = null): array;
}
