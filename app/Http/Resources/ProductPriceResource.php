<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'produto_origem_id' => $this->produto_origem_id,
            'codigo_produto' => $this->codigo_produto,
            'nome_produto' => $this->nome_produto,
            'categoria' => $this->categoria,
            'subcategoria' => $this->subcategoria,
            'descricao' => $this->descricao,
            'fabricante' => $this->fabricante,
            'modelo' => $this->modelo,
            'cor' => $this->cor,
            'peso_gramas' => $this->peso_gramas,
            'largura_cm' => $this->largura_cm,
            'altura_cm' => $this->altura_cm,
            'profundidade_cm' => $this->profundidade_cm,
            'unidade' => $this->unidade,
            'data_cadastro' => $this->data_cadastro,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'precos' => PriceInsertionResource::collection($this->whenLoaded('prices')),
        ];
    }
}
