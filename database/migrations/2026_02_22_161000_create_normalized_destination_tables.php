<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('produto_insercao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_origem_id')->unique();
            $table->string('codigo_produto', 30)->unique();
            $table->string('nome_produto', 150)->nullable();
            $table->string('categoria', 50)->nullable();
            $table->string('subcategoria', 50)->nullable();
            $table->text('descricao')->nullable();
            $table->string('fabricante', 100)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('cor', 30)->nullable();
            $table->decimal('peso_gramas', 10, 2)->nullable();
            $table->decimal('largura_cm', 8, 2)->nullable();
            $table->decimal('altura_cm', 8, 2)->nullable();
            $table->decimal('profundidade_cm', 8, 2)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->date('data_cadastro')->nullable();
            $table->timestamps();

            $table->index(['categoria', 'subcategoria']);
            $table->index('data_cadastro');
        });

        Schema::create('preco_insercao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('preco_origem_id')->unique();
            $table->foreignId('produto_insercao_id')
                ->constrained('produto_insercao')
                ->cascadeOnDelete();
            $table->decimal('valor', 12, 2)->nullable();
            $table->string('moeda', 10)->nullable();
            $table->decimal('desconto_percentual', 7, 4)->default(0);
            $table->decimal('acrescimo_percentual', 7, 4)->default(0);
            $table->decimal('valor_promocional', 12, 2)->nullable();
            $table->date('data_inicio_promocao')->nullable();
            $table->date('data_fim_promocao')->nullable();
            $table->date('data_atualizacao')->nullable();
            $table->string('origem', 50)->nullable();
            $table->string('tipo_cliente', 30)->nullable();
            $table->string('vendedor_responsavel', 100)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index('data_atualizacao');
            $table->index(['produto_insercao_id', 'tipo_cliente']);
            $table->index(['moeda', 'origem']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preco_insercao');
        Schema::dropIfExists('produto_insercao');
    }
};
