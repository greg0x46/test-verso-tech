<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListProductPricesRequest;
use App\Http\Resources\ProductPriceResource;
use App\Models\ProductInsertion;
use App\Support\ProductPriceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProductPriceController extends Controller
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 15;
    private const INDEX_CACHE_TTL_SECONDS = 300;

    public function index(ListProductPricesRequest $request): JsonResponse
    {
        $pagination = $this->normalizedPagination($request->validated());

        $cacheKey = ProductPriceCache::keyForIndex(
            $this->normalizedQueryString($pagination)
        );

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($cacheKey, self::INDEX_CACHE_TTL_SECONDS, function () use ($pagination): array {
            $paginator = ProductInsertion::query()
                ->with([
                    'prices' => static fn ($query) => $query->orderBy('id'),
                ])
                ->orderBy('id')
                ->paginate(
                    $pagination['per_page'],
                    ['*'],
                    'page',
                    $pagination['page']
                )
                ->appends($pagination);

            return ProductPriceResource::collection($paginator)
                ->response()
                ->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function normalizedQueryString(array $query): string
    {
        return http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{page: int, per_page: int}
     */
    private function normalizedPagination(array $validated): array
    {
        return [
            'page' => (int) ($validated['page'] ?? self::DEFAULT_PAGE),
            'per_page' => (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE),
        ];
    }
}
