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
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_produtos_base_sync_codigo_ativo
            ON produtos_base (UPPER(TRIM(prod_cod)), prod_id)
            WHERE prod_atv = 1
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_precos_base_sync_codigo_ativo
            ON precos_base (UPPER(TRIM(prc_cod_prod)))
            WHERE LOWER(TRIM(prc_status)) = 'ativo'
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_precos_base_sync_codigo_ativo');
        DB::statement('DROP INDEX IF EXISTS idx_produtos_base_sync_codigo_ativo');
    }
};
