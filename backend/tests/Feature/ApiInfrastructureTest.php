<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiInfrastructureTest extends TestCase
{
    public function test_health_uses_the_api_envelope(): void
    {
        $this->getJson('/api/health')->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Serviço disponível.',
            'data' => ['status' => 'ok'], 'errors' => [],
        ]);
    }

    public function test_unavailable_database_has_a_safe_503_response(): void
    {
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getPdo')->andThrow(new \RuntimeException('secret-db-password'));
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $this->getJson('/api/health')->assertStatus(503)->assertExactJson([
            'success' => false, 'message' => 'Serviço temporariamente indisponível.',
            'data' => null, 'errors' => [],
        ]);
    }

    public function test_unknown_route_and_wrong_method_return_json_even_without_accept_header(): void
    {
        $this->get('/api/not-found')->assertNotFound()->assertJsonPath('success', false)->assertJsonMissingPath('exception');
        $this->postJson('/api/health')->assertStatus(405)->assertHeader('Allow')->assertJsonPath('message', 'Método não permitido.');
    }

    public function test_rate_limit_returns_retry_after_and_the_standard_error(): void
    {
        RateLimiter::for('api', fn () => Limit::perMinute(1)->by('test-health'));
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/health')->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('success', false);
    }

    public function test_validation_errors_remain_attached_to_fields(): void
    {
        Route::post('/api/test-validation', function (Request $request) {
            $request->validate(['name' => 'required|string']);
        });
        $this->postJson('/api/test-validation', [])->assertUnprocessable()->assertJsonValidationErrors('name')->assertJsonPath('success', false);
    }

    public function test_internal_errors_do_not_expose_or_log_sensitive_messages(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        Route::get('/api/test-error', fn () => throw new \RuntimeException('secret-token-and-sql'));
        $response = $this->getJson('/api/test-error')->assertStatus(500)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('secret-token', $response->getContent());
        $response->assertJsonMissingPath('trace');
        Log::shouldHaveReceived('error')->once()->with('Falha interna da API.', ['exception' => \RuntimeException::class]);
    }

    public function test_openapi_describes_every_published_api_route(): void
    {
        $spec = json_decode(file_get_contents(base_path('docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $this->assertArrayHasKey('/'.$route->uri(), $spec['paths']);
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->assertArrayHasKey(strtolower($method), $spec['paths']['/'.$route->uri()]);
            }
        }
    }
}
