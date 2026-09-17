<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function __construct(private AuditService $audit) {}

    public function save(User $user, array $data, ?int $id = null): Transfer
    {
        return DB::transaction(function () use ($user, $data, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $record = $id ? Transfer::forUser($user->id)->lockForUpdate()->findOrFail($id) : new Transfer;
            if ($id && array_diff(array_keys($data), ['notes', 'description']) && $user->goalContributions()->where('transfer_id', $id)->whereNull('cancelled_at')->exists()) {
                throw ValidationException::withMessages(['transfer_id' => 'Cancele as contribuições vinculadas antes de alterar a transferência.']);
            }
            $record->fill($data);
            $record->user_id = $user->id;
            $record->save();
            $this->audit->record($user, 'transfers.'.($id ? 'updated' : 'created'), $record, array_keys($data));

            return $record->fresh();
        });
    }
}
