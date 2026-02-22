<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncProductsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_sync_consumes_views_and_performs_insert_update_delete_without_duplicates(): void
    {
        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 10)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0)
            ->assertJsonMissingPath('warnings');

        DB::table('produtos_base')
            ->where('prod_id', 1)
            ->update(['prod_nome' => '   Teclado   Mecanico   RGB   V2   ']);

        DB::table('produtos_base')
            ->where('prod_id', 2)
            ->update(['prod_atv' => 0]);

        $newProductId = DB::table('produtos_base')->insertGetId([
            'prod_cod' => ' prd013 ',
            'prod_nome' => '   Novo   Produto   Gamer   ',
            'prod_cat' => ' acessorios ',
            'prod_subcat' => ' suportes ',
            'prod_desc' => 'Produto criado para teste',
            'prod_fab' => 'Test Corp',
            'prod_mod' => 'TC-013',
            'prod_cor' => ' Preto ',
            'prod_peso' => '500g',
            'prod_larg' => '20cm',
            'prod_alt' => '10cm',
            'prod_prof' => '5cm',
            'prod_und' => ' un ',
            'prod_atv' => 1,
            'prod_dt_cad' => '2025/10/21',
        ]);

        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 1)
            ->assertJsonPath('atualizados', 1)
            ->assertJsonPath('removidos', 1)
            ->assertJsonMissingPath('warnings');

        $this->assertDatabaseCount('produto_insercao', 10);
        $this->assertDatabaseMissing('produto_insercao', ['produto_origem_id' => 2]);
        $this->assertDatabaseHas('produto_insercao', [
            'produto_origem_id' => $newProductId,
            'codigo_produto' => 'PRD013',
            'nome_produto' => 'Novo Produto Gamer',
        ]);
        $this->assertDatabaseHas('produto_insercao', [
            'produto_origem_id' => 1,
            'nome_produto' => 'Teclado Mecanico RGB V2',
        ]);

        $duplicateOriginIds = DB::table('produto_insercao')
            ->select('produto_origem_id')
            ->groupBy('produto_origem_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $duplicateCodes = DB::table('produto_insercao')
            ->select('codigo_produto')
            ->groupBy('codigo_produto')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicateOriginIds);
        $this->assertSame(0, $duplicateCodes);
    }

    public function test_products_sync_avoids_unnecessary_updates(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();

        $before = DB::table('produto_insercao')
            ->where('produto_origem_id', 1)
            ->value('updated_at');

        $this->travel(5)->minutes();

        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('inseridos', 0)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        $after = DB::table('produto_insercao')
            ->where('produto_origem_id', 1)
            ->value('updated_at');

        $this->assertSame($before, $after);
        $this->travelBack();
    }

    public function test_products_sync_handles_product_code_swap_between_two_origin_ids(): void
    {
        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('inseridos', 10);

        DB::table('produtos_base')
            ->where('prod_id', 1)
            ->update(['prod_cod' => 'PRD002']);

        DB::table('produtos_base')
            ->where('prod_id', 2)
            ->update(['prod_cod' => 'PRD001']);

        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 0)
            ->assertJsonPath('atualizados', 2)
            ->assertJsonPath('removidos', 0);

        $this->assertDatabaseHas('produto_insercao', [
            'codigo_produto' => 'PRD001',
            'produto_origem_id' => 2,
        ]);

        $this->assertDatabaseHas('produto_insercao', [
            'codigo_produto' => 'PRD002',
            'produto_origem_id' => 1,
        ]);

        $this->assertDatabaseCount('produto_insercao', 10);
        $this->assertSame(1, DB::table('produto_insercao')->where('produto_origem_id', 1)->count());
        $this->assertSame(1, DB::table('produto_insercao')->where('produto_origem_id', 2)->count());
    }

    public function test_produto_insercao_table_has_unique_constraint_for_produto_origem_id(): void
    {
        $indexes = DB::select("PRAGMA index_list('produto_insercao')");

        $hasUniqueConstraint = false;

        foreach ($indexes as $index) {
            $isUnique = (int) ($index->unique ?? 0) === 1;

            if (! $isUnique) {
                continue;
            }

            $indexName = $index->name ?? null;

            if (! is_string($indexName) || $indexName === '') {
                continue;
            }

            $columns = array_values(array_filter(array_map(
                static fn ($column): ?string => isset($column->name) ? (string) $column->name : null,
                DB::select("PRAGMA index_info('{$indexName}')")
            )));

            if ($columns === ['produto_origem_id']) {
                $hasUniqueConstraint = true;
                break;
            }
        }

        $this->assertTrue(
            $hasUniqueConstraint,
            'Era esperado um indice UNIQUE de coluna unica para produto_origem_id em produto_insercao.'
        );
    }

    public function test_produto_insercao_rejects_duplicate_produto_origem_id_insertion(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();

        $originId = DB::table('produto_insercao')
            ->where('codigo_produto', 'PRD001')
            ->value('produto_origem_id');

        $this->assertNotNull($originId);

        $this->expectException(QueryException::class);

        DB::table('produto_insercao')->insert([
            'produto_origem_id' => (int) $originId,
            'codigo_produto' => 'PRD001_DUP',
        ]);
    }

    public function test_products_sync_ignores_active_products_with_null_or_blank_code(): void
    {
        DB::table('produtos_base')->insert([
            'prod_cod' => null,
            'prod_nome' => 'Produto sem codigo',
            'prod_cat' => 'TESTE',
            'prod_subcat' => 'TESTE',
            'prod_desc' => null,
            'prod_fab' => null,
            'prod_mod' => null,
            'prod_cor' => null,
            'prod_peso' => null,
            'prod_larg' => null,
            'prod_alt' => null,
            'prod_prof' => null,
            'prod_und' => 'UN',
            'prod_atv' => 1,
            'prod_dt_cad' => null,
        ]);

        DB::table('produtos_base')->insert([
            'prod_cod' => '   ',
            'prod_nome' => 'Produto com codigo em branco',
            'prod_cat' => 'TESTE',
            'prod_subcat' => 'TESTE',
            'prod_desc' => null,
            'prod_fab' => null,
            'prod_mod' => null,
            'prod_cor' => null,
            'prod_peso' => null,
            'prod_larg' => null,
            'prod_alt' => null,
            'prod_prof' => null,
            'prod_und' => 'UN',
            'prod_atv' => 1,
            'prod_dt_cad' => null,
        ]);

        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 10)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        $this->assertDatabaseCount('produto_insercao', 10);
        $this->assertSame(0, DB::table('produto_insercao')->whereNull('codigo_produto')->count());
        $this->assertSame(0, DB::table('produto_insercao')->where('codigo_produto', '')->count());
    }

    public function test_products_sync_ignores_active_product_with_empty_code_string(): void
    {
        DB::table('produtos_base')->insert([
            'prod_cod' => '',
            'prod_nome' => 'Produto com codigo vazio',
            'prod_cat' => 'TESTE',
            'prod_subcat' => 'TESTE',
            'prod_desc' => null,
            'prod_fab' => null,
            'prod_mod' => null,
            'prod_cor' => null,
            'prod_peso' => null,
            'prod_larg' => null,
            'prod_alt' => null,
            'prod_prof' => null,
            'prod_und' => 'UN',
            'prod_atv' => 1,
            'prod_dt_cad' => null,
        ]);

        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 10)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        $this->assertDatabaseCount('produto_insercao', 10);
        $this->assertSame(0, DB::table('produto_insercao')->where('codigo_produto', '')->count());
    }
}
