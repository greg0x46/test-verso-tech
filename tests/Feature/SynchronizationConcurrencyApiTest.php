<?php

namespace Tests\Feature;

use App\Repositories\SynchronizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class SynchronizationConcurrencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_returns_conflict_when_lock_is_already_held(): void
    {
        $lock = Cache::lock('sync:catalog', 60);
        $this->assertTrue($lock->get());

        try {
            $this->postJson('/api/sincronizar/produtos')
                ->assertStatus(409)
                ->assertJsonPath('registros_processados', 0)
                ->assertJsonPath('inseridos', 0)
                ->assertJsonPath('atualizados', 0)
                ->assertJsonPath('removidos', 0);

            $this->postJson('/api/sincronizar/precos')
                ->assertStatus(409)
                ->assertJsonPath('registros_processados', 0)
                ->assertJsonPath('inseridos', 0)
                ->assertJsonPath('atualizados', 0)
                ->assertJsonPath('removidos', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_sync_returns_generic_internal_error_when_repository_throws(): void
    {
        $this->mock(SynchronizationRepository::class, function ($mock): void {
            $mock->shouldReceive('syncProducts')
                ->once()
                ->andThrow(new RuntimeException('forced failure'));
        });

        $this->postJson('/api/sincronizar/produtos')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Erro interno durante a sincronizacao.')
            ->assertJsonPath('registros_processados', 0)
            ->assertJsonPath('inseridos', 0)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);
    }

    public function test_sync_prices_returns_generic_internal_error_when_repository_throws(): void
    {
        $this->mock(SynchronizationRepository::class, function ($mock): void {
            $mock->shouldReceive('syncPrices')
                ->once()
                ->andThrow(new RuntimeException('forced failure'));
        });

        $this->postJson('/api/sincronizar/precos')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Erro interno durante a sincronizacao.')
            ->assertJsonPath('registros_processados', 0)
            ->assertJsonPath('inseridos', 0)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);
    }
}
