<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceInsertion extends Model
{
    protected $table = 'preco_insercao';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'preco_origem_id',
        'produto_insercao_id',
        'valor',
        'moeda',
        'desconto_percentual',
        'acrescimo_percentual',
        'valor_promocional',
        'data_inicio_promocao',
        'data_fim_promocao',
        'data_atualizacao',
        'origem',
        'tipo_cliente',
        'vendedor_responsavel',
        'observacao',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductInsertion::class, 'produto_insercao_id');
    }
}
