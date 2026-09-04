<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function created(Model $model): void
    {
        $this->auditService->logModelEvent('created', $model);
    }

    public function updated(Model $model): void
    {
        $old = collect($model->getOriginal())->only(
            array_keys($model->getChanges())
        )->toArray();

        $this->auditService->logModelEvent('updated', $model, $old);
    }

    public function deleted(Model $model): void
    {
        $this->auditService->logModelEvent('deleted', $model, $model->getAttributes());
    }

    public function restored(Model $model): void
    {
        $this->auditService->logModelEvent('restored', $model);
    }
}
