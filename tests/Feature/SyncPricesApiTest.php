<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncPricesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_prices_sync_without_products_returns_empty_payload(): void
    {
        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 0)
            ->assertJsonPath('inseridos', 0)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);
    }

    public function test_prices_sync_persists_sem_preco_with_cedilla_as_null(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();

        $newPriceId = DB::table('precos_base')->insertGetId([
            'prc_cod_prod' => ' prd001 ',
            'prc_valor' => ' sem preço ',
            'prc_moeda' => ' brl ',
            'prc_desc' => '0',
            'prc_acres' => '0',
            'prc_promo' => null,
            'prc_dt_ini_promo' => null,
            'prc_dt_fim_promo' => null,
            'prc_dt_atual' => '2025/10/26',
            'prc_origem' => 'api externa',
            'prc_tipo_cli' => 'varejo',
            'prc_vend_resp' => 'Alice Test',
            'prc_obs' => 'Preco sem valor numerico',
            'prc_status' => 'ativo',
        ]);

        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 11)
            ->assertJsonPath('inseridos', 11)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        $newPrice = DB::table('preco_insercao')
            ->where('preco_origem_id', $newPriceId)
            ->first(['valor', 'moeda']);

        $this->assertNotNull($newPrice);
        $this->assertNull($newPrice->valor);
        $this->assertSame('BRL', $newPrice->moeda);
    }

    public function test_prices_sync_parses_monetary_text_when_contains_digits(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();

        $newPriceId = DB::table('precos_base')->insertGetId([
            'prc_cod_prod' => ' prd001 ',
            'prc_valor' => 'R$ 1.234,56',
            'prc_moeda' => ' brl ',
            'prc_desc' => '0',
            'prc_acres' => '0',
            'prc_promo' => 'R$ 999,90',
            'prc_dt_ini_promo' => null,
            'prc_dt_fim_promo' => null,
            'prc_dt_atual' => '2025/10/26',
            'prc_origem' => 'api externa',
            'prc_tipo_cli' => 'varejo',
            'prc_vend_resp' => 'Alice Test',
            'prc_obs' => 'Preco com simbolo monetario',
            'prc_status' => 'ativo',
        ]);

        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 11)
            ->assertJsonPath('inseridos', 11)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        $newPrice = DB::table('preco_insercao')
            ->where('preco_origem_id', $newPriceId)
            ->first(['valor', 'valor_promocional']);

        $this->assertNotNull($newPrice);
        $this->assertEqualsWithDelta(1234.56, (float) $newPrice->valor, 0.0001);
        $this->assertEqualsWithDelta(999.90, (float) $newPrice->valor_promocional, 0.0001);
    }

    public function test_products_and_prices_sync_ignore_duplicate_product_codes_after_normalization(): void
    {
        DB::table('produtos_base')->insert([
            'prod_cod' => ' prd001 ',
            'prod_nome' => 'Produto Duplicado',
            'prod_cat' => 'PERIFERICOS',
            'prod_subcat' => 'TECLADOS',
            'prod_desc' => 'Duplicidade para teste',
            'prod_fab' => 'Test Corp',
            'prod_mod' => 'TST-001',
            'prod_cor' => 'PRETO',
            'prod_peso' => '1kg',
            'prod_larg' => '10cm',
            'prod_alt' => '5cm',
            'prod_prof' => '3cm',
            'prod_und' => 'UN',
            'prod_atv' => 1,
            'prod_dt_cad' => '2025-10-31',
        ]);

        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 10)
            ->assertJsonMissingPath('warnings');

        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 10);

        $this->assertDatabaseCount('produto_insercao', 10);
        $this->assertSame(
            1,
            DB::table('produto_insercao')->where('codigo_produto', 'PRD001')->count()
        );
    }

    public function test_products_and_prices_sync_merge_duplicate_codes_without_duplicate_prices(): void
    {
        DB::table('produtos_base')->insert([
            'prod_cod' => ' prd001 ',
            'prod_nome' => 'Produto Duplicado',
            'prod_cat' => 'PERIFERICOS',
            'prod_subcat' => 'TECLADOS',
            'prod_desc' => 'Duplicidade para teste',
            'prod_fab' => 'Test Corp',
            'prod_mod' => 'TST-001',
            'prod_cor' => 'PRETO',
            'prod_peso' => '1kg',
            'prod_larg' => '10cm',
            'prod_alt' => '5cm',
            'prod_prof' => '3cm',
            'prod_und' => 'UN',
            'prod_atv' => 1,
            'prod_dt_cad' => '2025-10-31',
        ]);

        $newPriceId = DB::table('precos_base')->insertGetId([
            'prc_cod_prod' => ' prd001 ',
            'prc_valor' => ' 459,90 ',
            'prc_moeda' => ' brl ',
            'prc_desc' => '0',
            'prc_acres' => '0',
            'prc_promo' => null,
            'prc_dt_ini_promo' => null,
            'prc_dt_fim_promo' => null,
            'prc_dt_atual' => '2025/10/25',
            'prc_origem' => 'api externa',
            'prc_tipo_cli' => 'varejo',
            'prc_vend_resp' => 'Alice Test',
            'prc_obs' => 'Preco adicional para merge',
            'prc_status' => 'ativo',
        ]);

        $this->postJson('/api/sincronizar/produtos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 10)
            ->assertJsonMissingPath('warnings');

        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 11)
            ->assertJsonPath('inseridos', 11)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        $this->assertDatabaseCount('produto_insercao', 10);
        $this->assertSame(
            1,
            DB::table('produto_insercao')->where('codigo_produto', 'PRD001')->count()
        );

        $mergedProductId = DB::table('produto_insercao')
            ->where('codigo_produto', 'PRD001')
            ->value('id');

        $this->assertNotNull($mergedProductId);

        $priceOriginIds = DB::table('preco_insercao')
            ->where('produto_insercao_id', $mergedProductId)
            ->orderBy('preco_origem_id')
            ->pluck('preco_origem_id')
            ->all();

        $this->assertSame([1, $newPriceId], $priceOriginIds);

        $duplicatePriceOriginIds = DB::table('preco_insercao')
            ->select('preco_origem_id')
            ->groupBy('preco_origem_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicatePriceOriginIds);
    }

    public function test_prices_sync_consumes_views_and_performs_insert_update_delete_without_duplicates(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();
        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 10)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        DB::table('precos_base')
            ->where('preco_id', 1)
            ->update([
                'prc_valor' => ' 599,90 ',
                'prc_moeda' => ' usd ',
            ]);

        DB::table('precos_base')
            ->where('preco_id', 2)
            ->update(['prc_status' => 'inativo']);

        $newPriceId = DB::table('precos_base')->insertGetId([
            'prc_cod_prod' => ' prd003 ',
            'prc_valor' => ' 777,77 ',
            'prc_moeda' => ' usd ',
            'prc_desc' => '0',
            'prc_acres' => '0',
            'prc_promo' => null,
            'prc_dt_ini_promo' => null,
            'prc_dt_fim_promo' => null,
            'prc_dt_atual' => '2025/10/25',
            'prc_origem' => 'api externa',
            'prc_tipo_cli' => 'varejo',
            'prc_vend_resp' => 'Alice Test',
            'prc_obs' => 'Preco criado para teste',
            'prc_status' => 'ativo',
        ]);

        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('registros_processados', 10)
            ->assertJsonPath('inseridos', 1)
            ->assertJsonPath('atualizados', 1)
            ->assertJsonPath('removidos', 1);

        $this->assertDatabaseCount('preco_insercao', 10);
        $this->assertDatabaseMissing('preco_insercao', ['preco_origem_id' => 2]);

        $updatedPrice = DB::table('preco_insercao')
            ->where('preco_origem_id', 1)
            ->first(['valor', 'moeda']);

        $newPrice = DB::table('preco_insercao')
            ->where('preco_origem_id', $newPriceId)
            ->first(['valor', 'moeda', 'tipo_cliente']);

        $this->assertNotNull($updatedPrice);
        $this->assertSame('USD', $updatedPrice->moeda);
        $this->assertEqualsWithDelta(599.90, (float) $updatedPrice->valor, 0.0001);

        $this->assertNotNull($newPrice);
        $this->assertSame('USD', $newPrice->moeda);
        $this->assertSame('VAREJO', $newPrice->tipo_cliente);
        $this->assertEqualsWithDelta(777.77, (float) $newPrice->valor, 0.0001);

        $duplicatePriceOriginIds = DB::table('preco_insercao')
            ->select('preco_origem_id')
            ->groupBy('preco_origem_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicatePriceOriginIds);
    }

    public function test_prices_sync_avoids_unnecessary_updates(): void
    {
        $this->postJson('/api/sincronizar/produtos')->assertOk();
        $this->postJson('/api/sincronizar/precos')->assertOk();

        $before = DB::table('preco_insercao')
            ->where('preco_origem_id', 1)
            ->value('updated_at');

        $this->travel(5)->minutes();

        $this->postJson('/api/sincronizar/precos')
            ->assertOk()
            ->assertJsonPath('inseridos', 0)
            ->assertJsonPath('atualizados', 0)
            ->assertJsonPath('removidos', 0);

        $after = DB::table('preco_insercao')
            ->where('preco_origem_id', 1)
            ->value('updated_at');

        $this->assertSame($before, $after);
        $this->travelBack();
    }
}
