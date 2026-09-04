<?php

namespace App\Services\Reports;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

class AuthorizedStoreScope
{
    public function __construct(
        public readonly User $user,
        public readonly Collection $stores,
    ) {
    }

    public static function forUser(User $user): self
    {
        // MVP: all stores within the user's tenant
        $stores = Store::where('tenant_id', $user->tenant_id)->get();

        return new self($user, $stores);
    }

    public function contains(?int $storeId): bool
    {
        if ($storeId === null) {
            return true;
        }

        return $this->stores->contains('id', $storeId);
    }

    public function only(array $storeIds): array
    {
        return array_intersect(
            $storeIds,
            $this->stores->pluck('id')->toArray()
        );
    }
}
