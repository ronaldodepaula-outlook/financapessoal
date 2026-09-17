<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Serviço temporariamente indisponível.',
                'data' => null,
                'errors' => [],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Serviço disponível.',
            'data' => ['status' => 'ok'],
            'errors' => [],
        ]);
    }
}
