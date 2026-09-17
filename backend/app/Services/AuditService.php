<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(User $user, string $action, Model $entity, array $changedFields = []): void
    {
        // Registrar nomes de campos, nunca credenciais, tokens ou payload completo.
        $fields = array_values(array_diff($changedFields, ['password', 'remember_token', 'refresh_token', 'refresh_token_hash', 'access_token']));
        $user->auditLogs()->create([
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => is_numeric($entity->getKey()) ? $entity->getKey() : null,
            'changes' => ['fields' => $fields],
        ]);
    }
}
