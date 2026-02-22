<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPricesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_paginated_products_with_prices(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();
        $this->postJson('/api/sincronizar/precos')->assertOk();

        $this->getJson('/api/produtos-precos?per_page=3&page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 10)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'produto_origem_id',
                        'codigo_produto',
                        'nome_produto',
                        'precos' => [
                            '*' => [
                                'id',
                                'preco_origem_id',
                                'produto_insercao_id',
                                'valor',
                                'moeda',
                            ],
                        ],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
            ]);
    }

    public function test_product_prices_cache_is_keyed_by_normalized_pagination_params(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();
        $this->postJson('/api/sincronizar/precos')->assertOk();

        $cachedName = $this->getJson('/api/produtos-precos?per_page=3&page=%2B1&ignored=foo')
            ->assertOk()
            ->json('data.0.nome_produto');

        $this->assertIsString($cachedName);
        $this->assertNotSame('Nome atualizado sem sync', $cachedName);

        DB::table('produto_insercao')
            ->where('codigo_produto', 'PRD001')
            ->update(['nome_produto' => 'Nome atualizado sem sync']);

        $this->getJson('/api/produtos-precos?ignored=bar&per_page=3&page=1')
            ->assertOk()
            ->assertJsonPath('data.0.nome_produto', $cachedName);

        $this->getJson('/api/produtos-precos?per_page=4&page=1')
            ->assertOk()
            ->assertJsonPath('data.0.nome_produto', 'Nome atualizado sem sync');
    }

    public function test_product_prices_cache_is_invalidated_after_sync_action(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();
        $this->postJson('/api/sincronizar/precos')->assertOk();

        $query = '/api/produtos-precos?per_page=3&page=1';

        $staleName = $this->getJson($query)
            ->assertOk()
            ->json('data.0.nome_produto');

        $this->assertIsString($staleName);
        $this->assertNotSame('Produto cache invalido apos sync', $staleName);

        DB::table('produtos_base')
            ->where('prod_id', 1)
            ->update(['prod_nome' => ' Produto cache invalido apos sync ']);

        $this->postJson('/api/sincronizar/produtos')->assertOk();

        $this->getJson($query)
            ->assertOk()
            ->assertJsonPath('data.0.nome_produto', 'Produto cache invalido apos sync');
    }

    public function test_rejects_invalid_pagination_params(): void
    {
        $this->getJson('/api/produtos-precos?per_page=101&page=0')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Parametros de paginacao invalidos.')
            ->assertJsonStructure([
                'errors' => [
                    'page',
                    'per_page',
                ],
            ]);
    }
}
