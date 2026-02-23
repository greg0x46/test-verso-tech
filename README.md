## Setup

1. Copie o arquivo modelo de variáveis de ambiente:
```bash
cp .env.example .env
```

2. (Opcional) Configure os IDs do host no `.env` (evita problemas de permissao em arquivos criados no container):
```bash
id -u
id -g
```

Adicione os valores no `.env`:
```env
WWWUSER=1000
WWWGROUP=1000
```

3. Instale as dependencias:
```bash
docker compose run --rm --build laravel.test composer install
```

4. Suba o ambiente:
```bash
docker compose up -d
```

5. Gere a chave da aplicacao:
```bash
docker compose exec laravel.test php artisan key:generate
```

6. Rode as migrations:
```bash
docker compose exec laravel.test php artisan migrate
```
docker compose restart laravel.test
## Documentacao da API

- OpenAPI (arquivo): [`public/openapi.yaml`](public/openapi.yaml)
- OpenAPI (servido pela aplicacao): [`/openapi.yaml`](/openapi.yaml)
- ReDoc (pagina publica): [`/api/docs`](/api/docs)

## Benchmark

### Objetivo

Avaliar a capacidade da API de sincronização em processar grandes volumes de dados e identificar os limites da abordagem síncrona atual.

Embora em cenários reais esse tipo de operação seja tipicamente executado de forma assíncrona (ex.: via fila ou job scheduler), este benchmark mede o desempenho da implementação atual sob execução direta via HTTP.

### Como reproduzir

Reset do banco:

```bash
docker compose exec -u sail laravel.test php artisan migrate:fresh --force
```

Executar baseline:

```bash
curl -X POST http://localhost/api/sincronizar/produtos
curl -X POST http://localhost/api/sincronizar/precos
```

Gerar carga de stress:

```bash
docker compose exec -u sail laravel.test php artisan base:gerar-alteracoes-sync --perfil=stress
```

Executar sincronização após cada geração de carga:

```bash
curl -X POST http://localhost/api/sincronizar/produtos
curl -X POST http://localhost/api/sincronizar/precos
```

Repetir o processo conforme necessário para aumentar progressivamente o volume de dados.

### Metodologia

Foram executadas:

- 1 rodada baseline com destino vazio
- 3 rodadas de stress progressivo usando o perfil `stress`
- Timeout setado em 30s

Cada rodada mede:

- tempo total de execução
- número de registros processados
- inserções, atualizações e remoções
- throughput aproximado

### Resultados

| Rodada | Endpoint | Tempo | Registros processados | Inseridos | Atualizados | Removidos | Throughput |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Baseline | produtos | 0.0647 s | 10 | 10 | 0 | 0 | 154 registros/s |
| Baseline | precos | 0.0281 s | 10 | 10 | 0 | 0 | 355 registros/s |
| Stress #1 | produtos | 9.3761 s | 360.010 | 360.000 | 10 | 0 | ~38.4k registros/s |
| Stress #1 | precos | 11.4158 s | 360.010 | 360.000 | 10 | 0 | ~31.5k registros/s |
| Stress #2 | produtos | 21.2710 s | 680.010 | 360.000 | 79.990 | 40.000 | ~32.0k registros/s |
| Stress #2 | precos | 23.1919 s | 640.010 | 360.000 | 79.990 | 40.000 | ~27.6k registros/s |
| Stress #3 | produtos | 27.7430 s | 1.000.010 | 360.000 | 79.990 | 40.000 | ~36.0k registros/s |
| Stress #3 | precos | 31.6157 s | n/a | n/a | n/a | n/a | timeout |

### Análise

Observações principais:

- A sincronização de produtos processou aproximadamente 1 milhão de registros em 27.7 segundos, mantendo throughput médio de aproximadamente 35 mil registros por segundo.
- O tempo de execução cresceu de forma aproximadamente linear com o aumento do volume, indicando boa escalabilidade da abordagem set-based.
- A sincronização de preços excedeu o timeout na terceira rodada, evidenciando maior custo relativo devido à complexidade adicional de joins e consolidação.

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
- Para reduzir custo de varreduras repetidas na origem, a sincronizacao passou a materializar a fonte em tabelas temporarias (`temp_sync_products_source` e `temp_sync_prices_source`) com indices locais para joins/contagens/delete/upsert.
- Essa materializacao temporaria removeu o gargalo principal de recalculo de CTE/subqueries em cada etapa do fluxo e reduziu substancialmente o tempo total por rodada.
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
- O comando de massa para benchmark (`base:gerar-alteracoes-sync`) ganhou perfis em ingles (`small|medium|large|stress`) e o perfil `stress` foi calibrado para provocar timeout proximo da 3a iteracao, permitindo testes de limite com menos rodadas.

#### Product-price listing

- A listagem em `app/Http/Controllers/Api/ProductPriceController.php` pagina produtos (nao linhas de join) com `with('prices')`, preservando o contrato 1:N de `produto -> precos` sem duplicar produtos na resposta.
- A serializacao foi centralizada em `app/Http/Resources/ProductPriceResource.php` e `app/Http/Resources/PriceInsertionResource.php`, mantendo contrato de resposta explicito e desacoplado da camada de persistencia.
- A paginacao foi validada em `app/Http/Requests/Api/ListProductPricesRequest.php` (`page >= 1`, `per_page <= 100`) com payload 422 padronizado para erros de entrada.
- A resposta paginada preserva os parametros normalizados de paginacao com `appends($pagination)`, mantendo consistencia nos links de navegacao.
- Foi aplicado cache de resposta por 300s com chave versionada por hash da query de paginacao normalizada em `app/Support/ProductPriceCache.php` (`page/per_page` via `http_build_query`).
