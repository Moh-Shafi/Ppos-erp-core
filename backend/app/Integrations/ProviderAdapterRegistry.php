<?php

namespace App\Integrations;

use App\Contracts\ProviderAdapterInterface;
use App\Integrations\Adapters\GenericHttpAdapter;
use InvalidArgumentException;

class ProviderAdapterRegistry
{
    protected array $adapters = [];

    public function __construct()
    {
        $this->register(GenericHttpAdapter::slug(), GenericHttpAdapter::class);
    }

    public function register(string $slug, string $class): void
    {
        if (!in_array(ProviderAdapterInterface::class, class_implements($class) ?: [])) {
            throw new InvalidArgumentException("{$class} must implement ProviderAdapterInterface");
        }
        $this->adapters[$slug] = $class;
    }

    public function resolve(string $slug): ProviderAdapterInterface
    {
        if (!isset($this->adapters[$slug])) {
            throw new InvalidArgumentException("No adapter registered for provider: {$slug}");
        }

        return new $this->adapters[$slug];
    }

    public function has(string $slug): bool
    {
        return isset($this->adapters[$slug]);
    }

    public function all(): array
    {
        return array_keys($this->adapters);
    }
}
