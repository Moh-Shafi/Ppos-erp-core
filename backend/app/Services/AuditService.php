<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
        ?int $tenantId = null,
    ): void {
        $user = Auth::user();

        $old = $old ? $this->redact($old) : null;
        $new = $new ? $this->redact($new) : null;

        AuditLog::create([
            'tenant_id' => $tenantId ?? $user?->tenant_id ?? ($new['tenant_id'] ?? null),
            'user_id' => $userId ?? $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'route' => Request::route()?->getName() ?? Request::path(),
            'method' => Request::method(),
        ]);
    }

    public function logModelEvent(string $action, Model $model, ?array $old = null): void
    {
        $newArray = $model->getAttributes();

        $this->log(
            $action,
            $model::class,
            $model->getKey(),
            $old,
            $newArray,
        );
    }

    public function listLogs(int $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['route'])) {
            $query->where('route', 'like', '%' . $filters['route'] . '%');
        }

        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        return $query->with('user:id,name,email')
            ->paginate($filters['per_page'] ?? 20);
    }

    private function redact(array $data): array
    {
        $redactedFields = config('audit.redacted_fields', []);

        foreach ($data as $key => $value) {
            if (in_array($key, $redactedFields, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
