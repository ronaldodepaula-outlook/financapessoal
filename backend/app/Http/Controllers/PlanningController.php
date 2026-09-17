<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InitialPlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanningController extends Controller
{
    public function initialize(Request $request, InitialPlanningService $service)
    {
        return ApiResponse::success($service->initialize($request->user()), 'Planejamento inicial preparado; configurações existentes preservadas.');
    }

    public function preferences(Request $request)
    {
        $row = $request->user()->userPreferences;

        return ApiResponse::success($row ?? ['income_is_net_of_payroll_loan' => null, 'timezone' => 'America/Sao_Paulo', 'currency' => 'BRL']);
    }

    public function updatePreferences(Request $request, AuditService $audit)
    {
        $data = $request->validate(['income_is_net_of_payroll_loan' => 'required|boolean', 'user_id' => 'prohibited']);
        $result = DB::transaction(function () use ($request, $data, $audit) {
            $user = $request->user();
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $data['income_is_net_of_payroll_loan'] && $user->loans()->where('cash_flow_mode', 'FOLHA')->where('status', 'ATIVO')->exists()) {
                throw ValidationException::withMessages(['income_is_net_of_payroll_loan' => 'Há consignado ativo configurado como desconto já abatido da renda. Preserve o tratamento do contrato.']);
            }
            $row = $user->userPreferences()->firstOrCreate([], ['timezone' => 'America/Sao_Paulo', 'currency' => 'BRL', 'income_is_net_of_payroll_loan' => null]);
            $row->update($data);
            $audit->record($user, 'preferences.updated', $row, array_keys($data));

            return $row;
        });

        return ApiResponse::success($result);
    }
}
