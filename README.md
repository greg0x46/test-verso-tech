## Setup

1. Configure os IDs do host no `.env` (evita problemas de permissao em arquivos criados no container):
```bash
id -u
id -g
```

Adicione os valores no `.env`:
```env
WWWUSER=1000
WWWGROUP=1000
```

2. Suba o ambiente:
```bash
docker compose up -d --build
```

3. Instale dependencias (caso ainda nao tenha feito):
```bash
docker compose exec laravel.test composer install
```

4. Gere a chave da aplicacao:
```bash
docker compose exec laravel.test php artisan key:generate
```

5. Rode as migrations:
```bash
docker compose exec laravel.test php artisan migrate
```

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
