<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sqlPath = database_path('dumps/base_scripts.sql');
        $sql = file_get_contents($sqlPath);

        if ($sql === false) {
            throw new RuntimeException("Unable to read SQL dump file: {$sqlPath}");
        }

        DB::unprepared($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('precos_base');
        Schema::dropIfExists('produtos_base');
    }
};
