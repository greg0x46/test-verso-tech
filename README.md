## Setup

1. Suba o ambiente:
```bash
docker compose up -d --build
```

2. Copie o arquivo de ambiente (via container):
```bash
docker compose exec laravel.test cp .env.example .env
```

3. (Opcional) Configure os IDs do host no `.env` (evita problemas de permissao em arquivos criados no container):
```bash
id -u
id -g
```

Adicione os valores no `.env`:
```env
WWWUSER=1000
WWWGROUP=1000
```

Se alterar esses valores, recrie o container para aplicar:
```bash
docker compose up -d --build --force-recreate
```

4. Instale dependencias (caso ainda nao tenha feito):
```bash
docker compose exec laravel.test composer install
```

5. Gere a chave da aplicacao:
```bash
docker compose exec laravel.test php artisan key:generate
```

6. Rode as migrations:
```bash
docker compose exec laravel.test php artisan migrate
```

## Documentacao da API

- OpenAPI (arquivo): [`public/openapi.yaml`](public/openapi.yaml)
- OpenAPI (servido pela aplicacao): [`/openapi.yaml`](/openapi.yaml)
- ReDoc (pagina publica): [`/api/docs`](/api/docs)

## Decision Notes


#### Environment

- Embora um dos requisitos seja utilizar o arquivo ``docker-compose.yml`` aqui estou usando ele como ``compose.yaml`` que é o padrão recomendado nas versões mais recentes.
- Pela praticidade e por se tratar de um ambiente de desenvolvimento aproveitei as imagens do laravel sail para montar o ambiente, apenas customizei o ``docker/8.5/start-container`` para criar nosso SQLite, dessa forma é possível usar tanto o ``docker compose`` diretamente quanto o utilitário do ``sail``. Em ambientes produtivos teria uma outra abordagem utilizando multi-staging building. 

#### Load dump SQL

- O dump `database/dumps/base_scripts.sql` foi ajustado para SQLite nos campos de chave primaria:
  - `prod_id SERIAL PRIMARY KEY` -> `prod_id INTEGER PRIMARY KEY AUTOINCREMENT`
  - `preco_id SERIAL PRIMARY KEY` -> `preco_id INTEGER PRIMARY KEY AUTOINCREMENT`
- Motivo: em SQLite, `SERIAL` nao gera auto incremento como no PostgreSQL, o que deixava `prod_id` e `preco_id` como `NULL` apos os inserts. Com `INTEGER PRIMARY KEY AUTOINCREMENT`, os IDs passam a ser preenchidos automaticamente.

- Eu poderia separar o arquivo `base_scripts.sql` em migrations e seeders, porém optei por executá-lo diretamente na migration pelos seguintes motivos:

  - **Fidelidade aos dados originais**: Evita erros humanos (ou IA) e garante que os dados sejam aplicados exatamente como definidos no script fonte.
  - **Eficiência**: Reduz o tempo e o risco envolvidos na conversão manual dos dados para seeders.
  - **Escalabilidade**: Em cenários reais, é comum lidar com grandes volumes de dados ou scripts legados, tornando inviável ou pouco prático convertê-los para seeders.
  - **Separação de responsabilidades pragmática**: Seeders são mais adequados para dados de aplicação, enquanto scripts SQL são mais apropriados para importar estruturas ou datasets base fornecidos externamente.

#### Views and normalization

- Em `database/migrations/2026_02_22_160000_create_normalized_product_and_price_views.php`, as views foram criadas para centralizar a transformacao dos dados no banco, conforme o requisito de usar SQL Views.
- A `vw_produtos` concentra normalizacao de campos textuais, unidades e data de cadastro, alem de filtrar somente produtos ativos (`prod_atv = 1`).
- A `ve_precos` normaliza moeda, valores e percentuais, aplica filtro de status ativo e faz `JOIN` com `vw_produtos` para manter consistencia entre preco ativo e produto ativo.
- Nos campos monetarios (`valor` e `valor_promocional`), a view utiliza `normalize_money(...)` para tentar parsear formatos mistos (ex.: `R$ 1.234,56`) e retornar `NULL` quando o campo nao tiver digitos numericos parseaveis.
- Para evitar replicacao de linhas de preco quando existirem produtos duplicados por codigo na `vw_produtos`, a `ve_precos` faz o `JOIN` com codigos de produto distintos ativos.
- Em `app/Providers/AppServiceProvider.php`, as normalizacoes de data e valor monetario foram extraidas para funcoes SQLite (`normalize_date` e `normalize_money`), registradas no evento `ConnectionEstablished` para evitar conexao eager com o banco durante bootstrap.

#### Destination tables and API wiring

- Foram criadas tabelas de destino normalizadas em `database/migrations/2026_02_22_161000_create_normalized_destination_tables.php` para desacoplar ingestao/sincronizacao das tabelas base.
- Em `produto_insercao`, foram aplicadas unicidades em `produto_origem_id` e `codigo_produto` para reforcar a regra de 1 registro por produto consolidado; `produto_origem_id`;
- Em `preco_insercao`, foi usada unicidade em `preco_origem_id`, FK com `cascadeOnDelete` para manter integridade quando um produto sincronizado deixa de existir, e `valor`/`valor_promocional` como `nullable` para suportar origem sem valor numerico.
- Os endpoints foram isolados em `routes/api.php` e registrados em `bootstrap/app.php`, mantendo separacao clara entre rotas web e API.

#### Sync

- A sincronizacao foi implementada com operacoes SQL set-based em `app/Repositories/SynchronizationRepository.php`, reduzindo processamento em PHP e diminuindo round-trips entre aplicacao e banco.
- A implementacao ficou mais verbosa que uma abordagem com `foreach + updateOrInsert`, mas essa complexidade e intencional por causa das regras de dominio e consistencia.
- Tradeoff da abordagem adotada:
  - **Mais complexidade de leitura**: as queries sao maiores e exigem mais cuidado de manutencao.
  - **Mais robustez em volume real**: evita N+1 queries (um loop com `exists/updateOrInsert` por linha), reduz lock churn e escala melhor.
  - **Consistencia transacional**: contadores e escrita acontecem no mesmo contexto, evitando resposta da API divergente do estado persistido.
  - **Regra de negocio fiel**: suporta consolidacao de produtos duplicados por `codigo_produto` e merge de precos para o produto consolidado.
  - **Idempotencia real**: o `DO UPDATE` so roda quando houve mudanca de campo, preservando `updated_at` e evitando escrita desnecessaria.
- O fluxo de sync para produtos e precos segue o mesmo padrao:
  - calcula contadores (`processados`, `inseridos`, `atualizados`, `removidos`);
  - remove registros que nao existem mais na origem (`DELETE` por diferenca);
  - aplica `UPSERT` com `ON CONFLICT ... DO UPDATE`.
- No sync de produtos, a consolidacao de duplicidades por `cod_produto` ocorre no repository (e nao na view): o `UPSERT` usa `codigo_produto` como chave de conflito e mantem um unico registro em `produto_insercao` para cada codigo (usando o menor `prod_id` como referencia).
- No sync de produtos, a fonte ignora registros ativos sem codigo valido (`NULL`, vazio ou apenas espacos), evitando violacao de `NOT NULL/UNIQUE` no destino.
- Como `produto_origem_id` tambem e unico no destino, antes do `UPSERT` o repository aplica um remapeamento temporario de `produto_origem_id` nos registros com troca de origem para evitar conflito transitorio em cenarios de swap de codigo.
- No sync de precos, os precos de produtos duplicados por codigo sao mesclados para esse unico produto de destino; alem disso, a fonte de sync de precos aplica deduplicacao por `preco_origem_id` para evitar insercoes/atualizacoes repetidas.
- Para evitar operacoes desnecessarias, o `DO UPDATE` so executa quando algum campo realmente mudou (`WHERE` com comparacao campo a campo), preservando idempotencia e evitando alterar `updated_at` sem necessidade.
- A camada HTTP foi mantida enxuta em `app/Http/Controllers/Api/SynchronizationController.php`, enquanto as regras de persistencia ficaram centralizadas no repository (separacao de responsabilidades e manutencao facilitada).
- No SQLite, foi adicionado `WHERE 1 = 1` antes de `ON CONFLICT` nas instrucoes `INSERT ... SELECT` para evitar erro de parser observado com essa combinacao.
- Foi adicionado lock de sincronizacao com `Cache::lock('sync:catalog', 300)` no controller para evitar overlap entre chamadas concorrentes de produtos e precos; quando o lock esta ocupado a API responde `409`.
- O controller tambem ganhou tratamento explicito de falhas de sincronizacao com retorno generico `500` e `report()` para observabilidade.
- Os contadores de sincronizacao (`processados`, `inseridos`, `atualizados`, `removidos`) são calculados no mesmo contexto transacional das operacoes de escrita, reduzindo chance de divergencia entre retorno da API e estado persistido.
- A invalidacao de cache da listagem (`ProductPriceCache::invalidate()`) acontece no `finally` do controller, garantindo refresh da versao mesmo quando a sincronizacao falha.

#### Product-price listing

- A listagem em `app/Http/Controllers/Api/ProductPriceController.php` pagina produtos (nao linhas de join) com `with('prices')`, preservando o contrato 1:N de `produto -> precos` sem duplicar produtos na resposta.
- A serializacao foi centralizada em `app/Http/Resources/ProductPriceResource.php` e `app/Http/Resources/PriceInsertionResource.php`, mantendo contrato de resposta explicito e desacoplado da camada de persistencia.
- A paginacao foi validada em `app/Http/Requests/Api/ListProductPricesRequest.php` (`page >= 1`, `per_page <= 100`) com payload 422 padronizado para erros de entrada.
- A resposta paginada preserva os parametros normalizados de paginacao com `appends($pagination)`, mantendo consistencia nos links de navegacao.
- Foi aplicado cache de resposta por 300s com chave versionada por hash da query de paginacao normalizada em `app/Support/ProductPriceCache.php` (`page/per_page` via `http_build_query`).
