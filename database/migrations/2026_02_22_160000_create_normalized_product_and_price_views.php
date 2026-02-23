<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS ve_precos');
        DB::statement('DROP VIEW IF EXISTS vw_produtos');

        DB::statement(<<<'SQL'
            CREATE VIEW vw_produtos AS
            WITH produtos_tratados AS (
                SELECT
                    prod_id,
                    UPPER(TRIM(prod_cod)) AS codigo_produto,
                    TRIM(REPLACE(REPLACE(REPLACE(prod_nome, '  ', ' '), '  ', ' '), '  ', ' ')) AS nome_produto,
                    UPPER(TRIM(prod_cat)) AS categoria,
                    UPPER(TRIM(prod_subcat)) AS subcategoria,
                    TRIM(prod_desc) AS descricao,
                    TRIM(prod_fab) AS fabricante,
                    UPPER(TRIM(prod_mod)) AS modelo,
                    UPPER(TRIM(prod_cor)) AS cor,
                    LOWER(REPLACE(TRIM(prod_peso), ' ', '')) AS peso_txt,
                    LOWER(REPLACE(TRIM(prod_larg), ' ', '')) AS largura_txt,
                    LOWER(REPLACE(TRIM(prod_alt), ' ', '')) AS altura_txt,
                    LOWER(REPLACE(TRIM(prod_prof), ' ', '')) AS profundidade_txt,
                    UPPER(TRIM(prod_und)) AS unidade,
                    TRIM(prod_dt_cad) AS data_cadastro_txt
                FROM produtos_base
                WHERE prod_atv = 1
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
                CASE
                    WHEN instr(peso_txt, 'kg') > 0 THEN CAST(REPLACE(REPLACE(peso_txt, 'kg', ''), ',', '.') AS REAL) * 1000
                    WHEN instr(peso_txt, 'g') > 0 THEN CAST(REPLACE(REPLACE(peso_txt, 'g', ''), ',', '.') AS REAL)
                    ELSE CAST(REPLACE(peso_txt, ',', '.') AS REAL)
                END AS peso_gramas,
                CAST(REPLACE(REPLACE(largura_txt, 'cm', ''), ',', '.') AS REAL) AS largura_cm,
                CAST(REPLACE(REPLACE(altura_txt, 'cm', ''), ',', '.') AS REAL) AS altura_cm,
                CAST(REPLACE(REPLACE(profundidade_txt, 'cm', ''), ',', '.') AS REAL) AS profundidade_cm,
                unidade,
                normalize_date(data_cadastro_txt) AS data_cadastro
            FROM produtos_tratados
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW ve_precos AS
            WITH precos_tratados AS (
                SELECT
                    preco_id,
                    UPPER(TRIM(prc_cod_prod)) AS codigo_produto,
                    TRIM(REPLACE(prc_valor, ' ', '')) AS valor_txt,
                    UPPER(TRIM(prc_moeda)) AS moeda,
                    TRIM(REPLACE(prc_desc, ' ', '')) AS desconto_txt,
                    TRIM(REPLACE(prc_acres, ' ', '')) AS acrescimo_txt,
                    TRIM(REPLACE(prc_promo, ' ', '')) AS valor_promocional_txt,
                    TRIM(prc_dt_ini_promo) AS data_inicio_promocao_txt,
                    TRIM(prc_dt_fim_promo) AS data_fim_promocao_txt,
                    TRIM(prc_dt_atual) AS data_atualizacao_txt,
                    UPPER(TRIM(prc_origem)) AS origem,
                    UPPER(TRIM(prc_tipo_cli)) AS tipo_cliente,
                    TRIM(REPLACE(REPLACE(REPLACE(prc_vend_resp, '  ', ' '), '  ', ' '), '  ', ' ')) AS vendedor_responsavel,
                    NULLIF(TRIM(prc_obs), '') AS observacao
                FROM precos_base
                WHERE LOWER(TRIM(prc_status)) = 'ativo'
            ),
            precos_normalizados AS (
                SELECT
                    preco_id,
                    codigo_produto,
                    normalize_money(valor_txt) AS valor,
                    moeda,
                    CASE
                        WHEN desconto_txt IS NULL OR desconto_txt = '' THEN 0
                        WHEN instr(desconto_txt, '%') > 0 THEN CAST(REPLACE(REPLACE(desconto_txt, '%', ''), ',', '.') AS REAL) / 100.0
                        WHEN CAST(REPLACE(desconto_txt, ',', '.') AS REAL) > 1 THEN CAST(REPLACE(desconto_txt, ',', '.') AS REAL) / 100.0
                        ELSE CAST(REPLACE(desconto_txt, ',', '.') AS REAL)
                    END AS desconto_percentual,
                    CASE
                        WHEN acrescimo_txt IS NULL OR acrescimo_txt = '' THEN 0
                        WHEN instr(acrescimo_txt, '%') > 0 THEN CAST(REPLACE(REPLACE(acrescimo_txt, '%', ''), ',', '.') AS REAL) / 100.0
                        WHEN CAST(REPLACE(acrescimo_txt, ',', '.') AS REAL) > 1 THEN CAST(REPLACE(acrescimo_txt, ',', '.') AS REAL) / 100.0
                        ELSE CAST(REPLACE(acrescimo_txt, ',', '.') AS REAL)
                    END AS acrescimo_percentual,
                    normalize_money(valor_promocional_txt) AS valor_promocional,
                    data_inicio_promocao_txt,
                    data_fim_promocao_txt,
                    data_atualizacao_txt,
                    origem,
                    tipo_cliente,
                    vendedor_responsavel,
                    observacao
                FROM precos_tratados
            )
            SELECT
                p.preco_id,
                p.codigo_produto,
                p.valor,
                p.moeda,
                p.desconto_percentual,
                p.acrescimo_percentual,
                p.valor_promocional,
                normalize_date(p.data_inicio_promocao_txt) AS data_inicio_promocao,
                normalize_date(p.data_fim_promocao_txt) AS data_fim_promocao,
                normalize_date(p.data_atualizacao_txt) AS data_atualizacao,
                p.origem,
                p.tipo_cliente,
                p.vendedor_responsavel,
                p.observacao
            FROM precos_normalizados p
            INNER JOIN (
                SELECT DISTINCT codigo_produto
                FROM vw_produtos
            ) pr ON pr.codigo_produto = p.codigo_produto
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ve_precos');
        DB::statement('DROP VIEW IF EXISTS vw_produtos');
    }
};
