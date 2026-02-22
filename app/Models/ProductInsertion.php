<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductInsertion extends Model
{
    protected $table = 'produto_insercao';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'produto_origem_id',
        'codigo_produto',
        'nome_produto',
        'categoria',
        'subcategoria',
        'descricao',
        'fabricante',
        'modelo',
        'cor',
        'peso_gramas',
        'largura_cm',
        'altura_cm',
        'profundidade_cm',
        'unidade',
        'data_cadastro',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(PriceInsertion::class, 'produto_insercao_id');
    }
}
