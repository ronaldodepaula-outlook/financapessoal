<?php

use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['jwt' => AuthenticateJwt::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => true);
        $exceptions->report(function (Throwable $exception) {
            Log::error('Falha interna da API.', ['exception' => $exception::class]);
        })->stop();
        $exceptions->respond(function (Response $response) {
            $status = $response->getStatusCode();
            $messages = [
                400 => 'Requisição inválida.',
                401 => 'Autenticação necessária.',
                403 => 'Acesso não permitido.',
                404 => 'Recurso não encontrado.',
                405 => 'Método não permitido.',
                409 => 'A operação conflita com registros existentes.',
                419 => 'Sessão expirada.',
                422 => 'Verifique os dados informados.',
                429 => 'Muitas requisições. Tente novamente em instantes.',
                503 => 'Serviço temporariamente indisponível.',
            ];
            $errors = $status === 422 && $response instanceof JsonResponse
                ? ($response->getData(true)['errors'] ?? []) : [];
            $result = response()->json([
                'success' => false,
                'message' => $messages[$status] ?? 'Não foi possível concluir a solicitação.',
                'data' => null,
                'errors' => $errors,
            ], $status);
            foreach (['Retry-After', 'Allow', 'WWW-Authenticate', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'] as $header) {
                if ($response->headers->has($header)) {
                    $result->headers->set($header, $response->headers->get($header));
                }
            }

            return $result;
        });
    })->create();
