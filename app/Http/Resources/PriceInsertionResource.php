<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceInsertionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'preco_origem_id' => $this->preco_origem_id,
            'produto_insercao_id' => $this->produto_insercao_id,
            'valor' => $this->valor,
            'moeda' => $this->moeda,
            'desconto_percentual' => $this->desconto_percentual,
            'acrescimo_percentual' => $this->acrescimo_percentual,
            'valor_promocional' => $this->valor_promocional,
            'data_inicio_promocao' => $this->data_inicio_promocao,
            'data_fim_promocao' => $this->data_fim_promocao,
            'data_atualizacao' => $this->data_atualizacao,
            'origem' => $this->origem,
            'tipo_cliente' => $this->tipo_cliente,
            'vendedor_responsavel' => $this->vendedor_responsavel,
            'observacao' => $this->observacao,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
