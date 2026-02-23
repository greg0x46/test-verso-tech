<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class SynchronizationRepository
{
    private const TEMP_PRODUCT_SOURCE_TABLE = 'temp_sync_products_source';

    private const TEMP_PRICE_SOURCE_TABLE = 'temp_sync_prices_source';

    /**
     * @return array{processed:int, inserted:int, updated:int, deleted:int}
     */
    public function syncProducts(): array
    {
        $sourceSql = $this->productSourceSql();
        $sourceDifferenceSql = <<<'SQL'
            CAST(pi.produto_origem_id AS INTEGER) IS NOT CAST(src.prod_id AS INTEGER)
            OR pi.nome_produto IS NOT src.nome_produto
            OR pi.categoria IS NOT src.categoria
            OR pi.subcategoria IS NOT src.subcategoria
            OR pi.descricao IS NOT src.descricao
            OR pi.fabricante IS NOT src.fabricante
            OR pi.modelo IS NOT src.modelo
            OR pi.cor IS NOT src.cor
            OR CAST(pi.peso_gramas AS REAL) IS NOT CAST(src.peso_gramas AS REAL)
            OR CAST(pi.largura_cm AS REAL) IS NOT CAST(src.largura_cm AS REAL)
            OR CAST(pi.altura_cm AS REAL) IS NOT CAST(src.altura_cm AS REAL)
            OR CAST(pi.profundidade_cm AS REAL) IS NOT CAST(src.profundidade_cm AS REAL)
            OR pi.unidade IS NOT src.unidade
            OR pi.data_cadastro IS NOT src.data_cadastro
        SQL;

        $upsertDifferenceSql = <<<'SQL'
            CAST(produto_insercao.produto_origem_id AS INTEGER) IS NOT CAST(excluded.produto_origem_id AS INTEGER)
            OR produto_insercao.nome_produto IS NOT excluded.nome_produto
            OR produto_insercao.categoria IS NOT excluded.categoria
            OR produto_insercao.subcategoria IS NOT excluded.subcategoria
            OR produto_insercao.descricao IS NOT excluded.descricao
            OR produto_insercao.fabricante IS NOT excluded.fabricante
            OR produto_insercao.modelo IS NOT excluded.modelo
            OR produto_insercao.cor IS NOT excluded.cor
            OR CAST(produto_insercao.peso_gramas AS REAL) IS NOT CAST(excluded.peso_gramas AS REAL)
            OR CAST(produto_insercao.largura_cm AS REAL) IS NOT CAST(excluded.largura_cm AS REAL)
            OR CAST(produto_insercao.altura_cm AS REAL) IS NOT CAST(excluded.altura_cm AS REAL)
            OR CAST(produto_insercao.profundidade_cm AS REAL) IS NOT CAST(excluded.profundidade_cm AS REAL)
            OR produto_insercao.unidade IS NOT excluded.unidade
            OR produto_insercao.data_cadastro IS NOT excluded.data_cadastro
        SQL;

        $this->prepareProductSourceTable($sourceSql);

        try {
            return DB::transaction(function () use ($sourceDifferenceSql, $upsertDifferenceSql): array {
                $sourceQuery = DB::table(self::TEMP_PRODUCT_SOURCE_TABLE.' AS src');

                $processed = (clone $sourceQuery)->count();

                $inserted = (clone $sourceQuery)
                    ->leftJoin('produto_insercao AS pi', 'pi.codigo_produto', '=', 'src.codigo_produto')
                    ->whereNull('pi.codigo_produto')
                    ->count();

                $updated = (clone $sourceQuery)
                    ->join('produto_insercao AS pi', 'pi.codigo_produto', '=', 'src.codigo_produto')
                    ->whereRaw($sourceDifferenceSql)
                    ->count();

                $staleProductCodesQuery = DB::table('produto_insercao AS pi')
                    ->leftJoin(self::TEMP_PRODUCT_SOURCE_TABLE.' AS src', function ($join): void {
                        $join->on('src.codigo_produto', '=', 'pi.codigo_produto');
                    })
                    ->whereNull('src.codigo_produto')
                    ->select('pi.codigo_produto');

                $deleted = (clone $staleProductCodesQuery)->count();

                DB::table('produto_insercao')
                    ->whereIn('codigo_produto', $staleProductCodesQuery)
                    ->delete();

                DB::statement(<<<SQL
                UPDATE produto_insercao
                SET produto_origem_id = (
                    SELECT COALESCE(MAX(src.prod_id), 0)
                    FROM temp_sync_products_source src
                ) + id
                WHERE codigo_produto IN (
                    SELECT src.codigo_produto
                    FROM temp_sync_products_source src
                    INNER JOIN produto_insercao pi ON pi.codigo_produto = src.codigo_produto
                    WHERE CAST(pi.produto_origem_id AS INTEGER) IS NOT CAST(src.prod_id AS INTEGER)
                )
            SQL);

                DB::statement(<<<SQL
                INSERT INTO produto_insercao (
                    produto_origem_id,
                    codigo_produto,
                    nome_produto,
                    categoria,
                    subcategoria,
                    descricao,
                    fabricante,
                    modelo,
                    cor,
                    peso_gramas,
                    largura_cm,
                    altura_cm,
                    profundidade_cm,
                    unidade,
                    data_cadastro,
                    created_at,
                    updated_at
                )
                SELECT
                    src.prod_id,
                    src.codigo_produto,
                    src.nome_produto,
                    src.categoria,
                    src.subcategoria,
                    src.descricao,
                    src.fabricante,
                    src.modelo,
                    src.cor,
                    src.peso_gramas,
                    src.largura_cm,
                    src.altura_cm,
                    src.profundidade_cm,
                    src.unidade,
                    src.data_cadastro,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                FROM temp_sync_products_source src
                WHERE 1 = 1
                ON CONFLICT(codigo_produto) DO UPDATE SET
                    produto_origem_id = excluded.produto_origem_id,
                    nome_produto = excluded.nome_produto,
                    categoria = excluded.categoria,
                    subcategoria = excluded.subcategoria,
                    descricao = excluded.descricao,
                    fabricante = excluded.fabricante,
                    modelo = excluded.modelo,
                    cor = excluded.cor,
                    peso_gramas = excluded.peso_gramas,
                    largura_cm = excluded.largura_cm,
                    altura_cm = excluded.altura_cm,
                    profundidade_cm = excluded.profundidade_cm,
                    unidade = excluded.unidade,
                    data_cadastro = excluded.data_cadastro,
                    updated_at = CURRENT_TIMESTAMP
                WHERE
                    $upsertDifferenceSql
            SQL);

                return $this->syncStats($processed, $inserted, $updated, $deleted);
            });
        } finally {
            $this->dropTempTable(self::TEMP_PRODUCT_SOURCE_TABLE);
        }
    }

    /**
     * @return array{processed:int, inserted:int, updated:int, deleted:int}
     */
    public function syncPrices(): array
    {
        $sourceSql = $this->priceSourceSql();
        $sourceDifferenceSql = <<<'SQL'
            CAST(pi.produto_insercao_id AS INTEGER) IS NOT CAST(src.produto_insercao_id AS INTEGER)
            OR CAST(pi.valor AS REAL) IS NOT CAST(src.valor AS REAL)
            OR pi.moeda IS NOT src.moeda
            OR CAST(pi.desconto_percentual AS REAL) IS NOT CAST(src.desconto_percentual AS REAL)
            OR CAST(pi.acrescimo_percentual AS REAL) IS NOT CAST(src.acrescimo_percentual AS REAL)
            OR CAST(pi.valor_promocional AS REAL) IS NOT CAST(src.valor_promocional AS REAL)
            OR pi.data_inicio_promocao IS NOT src.data_inicio_promocao
            OR pi.data_fim_promocao IS NOT src.data_fim_promocao
            OR pi.data_atualizacao IS NOT src.data_atualizacao
            OR pi.origem IS NOT src.origem
            OR pi.tipo_cliente IS NOT src.tipo_cliente
            OR pi.vendedor_responsavel IS NOT src.vendedor_responsavel
            OR pi.observacao IS NOT src.observacao
        SQL;

        $upsertDifferenceSql = <<<'SQL'
            CAST(preco_insercao.produto_insercao_id AS INTEGER) IS NOT CAST(excluded.produto_insercao_id AS INTEGER)
            OR CAST(preco_insercao.valor AS REAL) IS NOT CAST(excluded.valor AS REAL)
            OR preco_insercao.moeda IS NOT excluded.moeda
            OR CAST(preco_insercao.desconto_percentual AS REAL) IS NOT CAST(excluded.desconto_percentual AS REAL)
            OR CAST(preco_insercao.acrescimo_percentual AS REAL) IS NOT CAST(excluded.acrescimo_percentual AS REAL)
            OR CAST(preco_insercao.valor_promocional AS REAL) IS NOT CAST(excluded.valor_promocional AS REAL)
            OR preco_insercao.data_inicio_promocao IS NOT excluded.data_inicio_promocao
            OR preco_insercao.data_fim_promocao IS NOT excluded.data_fim_promocao
            OR preco_insercao.data_atualizacao IS NOT excluded.data_atualizacao
            OR preco_insercao.origem IS NOT excluded.origem
            OR preco_insercao.tipo_cliente IS NOT excluded.tipo_cliente
            OR preco_insercao.vendedor_responsavel IS NOT excluded.vendedor_responsavel
            OR preco_insercao.observacao IS NOT excluded.observacao
        SQL;

        $this->preparePriceSourceTable($sourceSql);

        try {
            return DB::transaction(function () use ($sourceDifferenceSql, $upsertDifferenceSql): array {
                $sourceQuery = DB::table(self::TEMP_PRICE_SOURCE_TABLE.' AS src');

                $processed = (clone $sourceQuery)->count();

                $inserted = (clone $sourceQuery)
                    ->leftJoin('preco_insercao AS pi', 'pi.preco_origem_id', '=', 'src.preco_origem_id')
                    ->whereNull('pi.preco_origem_id')
                    ->count();

                $updated = (clone $sourceQuery)
                    ->join('preco_insercao AS pi', 'pi.preco_origem_id', '=', 'src.preco_origem_id')
                    ->whereRaw($sourceDifferenceSql)
                    ->count();

                $stalePriceOriginIdsQuery = DB::table('preco_insercao AS pi')
                    ->leftJoin(self::TEMP_PRICE_SOURCE_TABLE.' AS src', function ($join): void {
                        $join->on('src.preco_origem_id', '=', 'pi.preco_origem_id');
                    })
                    ->whereNull('src.preco_origem_id')
                    ->select('pi.preco_origem_id');

                $deleted = (clone $stalePriceOriginIdsQuery)->count();

                DB::table('preco_insercao')
                    ->whereIn('preco_origem_id', $stalePriceOriginIdsQuery)
                    ->delete();

                DB::statement(<<<SQL
                INSERT INTO preco_insercao (
                    preco_origem_id,
                    produto_insercao_id,
                    valor,
                    moeda,
                    desconto_percentual,
                    acrescimo_percentual,
                    valor_promocional,
                    data_inicio_promocao,
                    data_fim_promocao,
                    data_atualizacao,
                    origem,
                    tipo_cliente,
                    vendedor_responsavel,
                    observacao,
                    created_at,
                    updated_at
                )
                SELECT
                    src.preco_origem_id,
                    src.produto_insercao_id,
                    src.valor,
                    src.moeda,
                    src.desconto_percentual,
                    src.acrescimo_percentual,
                    src.valor_promocional,
                    src.data_inicio_promocao,
                    src.data_fim_promocao,
                    src.data_atualizacao,
                    src.origem,
                    src.tipo_cliente,
                    src.vendedor_responsavel,
                    src.observacao,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                FROM temp_sync_prices_source src
                WHERE 1 = 1
                ON CONFLICT(preco_origem_id) DO UPDATE SET
                    produto_insercao_id = excluded.produto_insercao_id,
                    valor = excluded.valor,
                    moeda = excluded.moeda,
                    desconto_percentual = excluded.desconto_percentual,
                    acrescimo_percentual = excluded.acrescimo_percentual,
                    valor_promocional = excluded.valor_promocional,
                    data_inicio_promocao = excluded.data_inicio_promocao,
                    data_fim_promocao = excluded.data_fim_promocao,
                    data_atualizacao = excluded.data_atualizacao,
                    origem = excluded.origem,
                    tipo_cliente = excluded.tipo_cliente,
                    vendedor_responsavel = excluded.vendedor_responsavel,
                    observacao = excluded.observacao,
                    updated_at = CURRENT_TIMESTAMP
                WHERE
                    $upsertDifferenceSql
            SQL);

                return $this->syncStats($processed, $inserted, $updated, $deleted);
            });
        } finally {
            $this->dropTempTable(self::TEMP_PRICE_SOURCE_TABLE);
        }
    }

    /**
     * @return array{processed:int, inserted:int, updated:int, deleted:int}
     */
    private function syncStats(int $processed, int $inserted, int $updated, int $deleted): array
    {
        return [
            'processed' => $processed,
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    private function prepareProductSourceTable(string $sourceSql): void
    {
        $this->recreateTempTable(self::TEMP_PRODUCT_SOURCE_TABLE, $sourceSql);

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_temp_sync_products_source_codigo
            ON temp_sync_products_source (codigo_produto)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_temp_sync_products_source_prod_id
            ON temp_sync_products_source (prod_id)
        SQL);
    }

    private function preparePriceSourceTable(string $sourceSql): void
    {
        $this->recreateTempTable(self::TEMP_PRICE_SOURCE_TABLE, $sourceSql);

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_temp_sync_prices_source_preco_origem_id
            ON temp_sync_prices_source (preco_origem_id)
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_temp_sync_prices_source_produto_insercao_id
            ON temp_sync_prices_source (produto_insercao_id)
        SQL);
    }

    private function recreateTempTable(string $tableName, string $sourceSql): void
    {
        $this->dropTempTable($tableName);
        DB::statement("CREATE TEMP TABLE {$tableName} AS {$sourceSql}");
    }

    private function dropTempTable(string $tableName): void
    {
        DB::statement("DROP TABLE IF EXISTS {$tableName}");
    }

    private function productSourceSql(): string
    {
        return <<<'SQL'
            WITH produtos_rankeados AS (
                SELECT
                    vp.prod_id,
                    vp.codigo_produto,
                    vp.nome_produto,
                    vp.categoria,
                    vp.subcategoria,
                    vp.descricao,
                    vp.fabricante,
                    vp.modelo,
                    vp.cor,
                    vp.peso_gramas,
                    vp.largura_cm,
                    vp.altura_cm,
                    vp.profundidade_cm,
                    vp.unidade,
                    vp.data_cadastro,
                    ROW_NUMBER() OVER (
                        PARTITION BY vp.codigo_produto
                        ORDER BY vp.prod_id ASC
                    ) AS codigo_rank
                FROM vw_produtos vp
                WHERE vp.codigo_produto IS NOT NULL
                  AND vp.codigo_produto <> ''
            )
            SELECT
                prod_id,
                codigo_produto,
                nome_produto,
                categoria,
                subcategoria,
                descricao,
                fabricante,
                modelo,
                cor,
                peso_gramas,
                largura_cm,
                altura_cm,
                profundidade_cm,
                unidade,
                data_cadastro
            FROM produtos_rankeados
            WHERE codigo_rank = 1
        SQL;
    }

    private function priceSourceSql(): string
    {
        return <<<'SQL'
            WITH precos_deduplicados AS (
                SELECT DISTINCT
                    vp.preco_id AS preco_origem_id,
                    vp.codigo_produto,
                    vp.valor,
                    vp.moeda,
                    vp.desconto_percentual,
                    vp.acrescimo_percentual,
                    vp.valor_promocional,
                    vp.data_inicio_promocao,
                    vp.data_fim_promocao,
                    vp.data_atualizacao,
                    vp.origem,
                    vp.tipo_cliente,
                    vp.vendedor_responsavel,
                    vp.observacao
                FROM ve_precos vp
            )
            SELECT
                pd.preco_origem_id,
                pi.id AS produto_insercao_id,
                pd.valor,
                pd.moeda,
                pd.desconto_percentual,
                pd.acrescimo_percentual,
                pd.valor_promocional,
                pd.data_inicio_promocao,
                pd.data_fim_promocao,
                pd.data_atualizacao,
                pd.origem,
                pd.tipo_cliente,
                pd.vendedor_responsavel,
                pd.observacao
            FROM precos_deduplicados pd
            INNER JOIN produto_insercao pi ON pi.codigo_produto = pd.codigo_produto
        SQL;
    }
}
