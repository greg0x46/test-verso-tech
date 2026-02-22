<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\SynchronizationRepository;
use App\Support\ProductPriceCache;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SynchronizationController extends Controller
{
    private const SYNC_LOCK_KEY = 'sync:catalog';
    private const SYNC_LOCK_TTL_SECONDS = 300;

    public function __construct(
        private readonly SynchronizationRepository $repository
    ) {
    }

    public function syncProducts(): JsonResponse
    {
        $lock = Cache::lock(self::SYNC_LOCK_KEY, self::SYNC_LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            return $this->syncAlreadyRunningResponse();
        }

        try {
            $stats = $this->repository->syncProducts();

            return response()->json([
                'message' => $stats['processed'] === 0
                    ? 'Nenhum produto elegivel encontrado para sincronizacao.'
                    : 'Produtos sincronizados com sucesso.',
                'registros_processados' => $stats['processed'],
                'inseridos' => $stats['inserted'],
                'atualizados' => $stats['updated'],
                'removidos' => $stats['deleted'],
            ]);
        } catch (Throwable $exception) {
            return $this->syncErrorResponse($exception);
        } finally {
            $this->releaseLock($lock);
            ProductPriceCache::invalidate();
        }
    }

    public function syncPrices(): JsonResponse
    {
        $lock = Cache::lock(self::SYNC_LOCK_KEY, self::SYNC_LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            return $this->syncAlreadyRunningResponse();
        }

        try {
            $stats = $this->repository->syncPrices();

            return response()->json([
                'message' => $stats['processed'] === 0
                    ? 'Nenhum preco elegivel encontrado para sincronizacao.'
                    : 'Precos sincronizados com sucesso.',
                'registros_processados' => $stats['processed'],
                'inseridos' => $stats['inserted'],
                'atualizados' => $stats['updated'],
                'removidos' => $stats['deleted'],
            ]);
        } catch (Throwable $exception) {
            return $this->syncErrorResponse($exception);
        } finally {
            $this->releaseLock($lock);
            ProductPriceCache::invalidate();
        }
    }

    private function releaseLock(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable) {
            // Sem impacto funcional para o cliente; lock expira por TTL.
        }
    }

    private function syncAlreadyRunningResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Uma sincronizacao ja esta em andamento. Tente novamente em instantes.',
            'registros_processados' => 0,
            'inseridos' => 0,
            'atualizados' => 0,
            'removidos' => 0,
        ], 409);
    }

    private function syncErrorResponse(Throwable $exception): JsonResponse
    {
        report($exception);

        return response()->json([
            'message' => 'Erro interno durante a sincronizacao.',
            'registros_processados' => 0,
            'inseridos' => 0,
            'atualizados' => 0,
            'removidos' => 0,
        ], 500);
    }
}
