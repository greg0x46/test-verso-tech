<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateSyncChangesCommand extends Command
{
    private const PROFILE_CUSTOM = 'custom';

    private const DEFAULT_NEW_PRODUCTS = 1000;

    private const DEFAULT_UPDATED_PRODUCTS = 500;

    private const DEFAULT_DEACTIVATED_PRODUCTS = 500;

    private const DEFAULT_NEW_PRICES = 50;

    private const DEFAULT_UPDATED_PRICES = 50;

    private const DEFAULT_DEACTIVATED_PRICES = 50;

    /**
     * @var array<string, array{
     *     new_products:int,
     *     update_products:int,
     *     deactivate_products:int,
     *     new_prices:int,
     *     update_prices:int,
     *     deactivate_prices:int
     * }>
     */
    private const PROFILES = [
        'small' => [
            'new_products' => 15000,
            'update_products' => 3000,
            'deactivate_products' => 1000,
            'new_prices' => 15000,
            'update_prices' => 3000,
            'deactivate_prices' => 1000,
        ],
        'medium' => [
            'new_products' => 80000,
            'update_products' => 15000,
            'deactivate_products' => 5000,
            'new_prices' => 80000,
            'update_prices' => 15000,
            'deactivate_prices' => 5000,
        ],
        'large' => [
            'new_products' => 200000,
            'update_products' => 50000,
            'deactivate_products' => 20000,
            'new_prices' => 200000,
            'update_prices' => 50000,
            'deactivate_prices' => 20000,
        ],
        'stress' => [
            'new_products' => 360000,
            'update_products' => 80000,
            'deactivate_products' => 40000,
            'new_prices' => 360000,
            'update_prices' => 80000,
            'deactivate_prices' => 40000,
        ],
    ];

    /**
     * @var array{
     *     new_products:int,
     *     update_products:int,
     *     deactivate_products:int,
     *     new_prices:int,
     *     update_prices:int,
     *     deactivate_prices:int
     * }
     */
    private const DEFAULT_PLAN = [
        'new_products' => self::DEFAULT_NEW_PRODUCTS,
        'update_products' => self::DEFAULT_UPDATED_PRODUCTS,
        'deactivate_products' => self::DEFAULT_DEACTIVATED_PRODUCTS,
        'new_prices' => self::DEFAULT_NEW_PRICES,
        'update_prices' => self::DEFAULT_UPDATED_PRICES,
        'deactivate_prices' => self::DEFAULT_DEACTIVATED_PRICES,
    ];

    protected $signature = 'base:gerar-alteracoes-sync
                            {--perfil='.self::PROFILE_CUSTOM.' : Perfil de carga: custom|small|medium|large|stress}
                            {--novos-produtos= : Quantidade de produtos novos}
                            {--atualizar-produtos= : Quantidade de produtos ativos para atualizar}
                            {--inativar-produtos= : Quantidade de produtos ativos para inativar}
                            {--novos-precos= : Quantidade de precos novos}
                            {--atualizar-precos= : Quantidade de precos ativos para atualizar}
                            {--inativar-precos= : Quantidade de precos ativos para inativar}';

    protected $description = 'Gera alteracoes em produtos_base e precos_base para exercitar os endpoints de sincronizacao.';

    public function handle(): int
    {
        $plan = $this->resolvePlan();

        if ($plan === null) {
            return self::FAILURE;
        }

        $productStats = [
            'inserted' => 0,
            'updated' => 0,
            'deactivated' => 0,
        ];

        $priceStats = [
            'inserted' => 0,
            'updated' => 0,
            'deactivated' => 0,
        ];

        DB::transaction(function () use ($plan, &$productStats, &$priceStats): void {
            $productStats = $this->applyProductChanges(
                $plan['new_products'],
                $plan['update_products'],
                $plan['deactivate_products']
            );

            $priceStats = $this->applyPriceChanges(
                $plan['new_prices'],
                $plan['update_prices'],
                $plan['deactivate_prices']
            );
        });

        $this->info("Produtos inseridos: {$productStats['inserted']}");
        $this->info("Produtos atualizados: {$productStats['updated']}");
        $this->info("Produtos inativados: {$productStats['deactivated']}");
        $this->info("Precos inseridos: {$priceStats['inserted']}");
        $this->info("Precos atualizados: {$priceStats['updated']}");
        $this->info("Precos inativados: {$priceStats['deactivated']}");
        $this->line('Alteracoes geradas com sucesso. Rode os endpoints de sync para processar o delta.');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     new_products:int,
     *     update_products:int,
     *     deactivate_products:int,
     *     new_prices:int,
     *     update_prices:int,
     *     deactivate_prices:int
     * }|null
     */
    private function resolvePlan(): ?array
    {
        $profile = $this->resolveProfile();

        if ($profile === null) {
            return null;
        }

        $mapping = [
            'new_products' => 'novos-produtos',
            'update_products' => 'atualizar-produtos',
            'deactivate_products' => 'inativar-produtos',
            'new_prices' => 'novos-precos',
            'update_prices' => 'atualizar-precos',
            'deactivate_prices' => 'inativar-precos',
        ];

        $plan = $profile === self::PROFILE_CUSTOM
            ? self::DEFAULT_PLAN
            : self::PROFILES[$profile];

        if ($profile !== self::PROFILE_CUSTOM) {
            $this->line("Perfil aplicado: {$profile}");
        }

        foreach ($mapping as $planKey => $optionName) {
            $rawValue = $this->option($optionName);

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $parsed = filter_var($rawValue, FILTER_VALIDATE_INT);

            if (! is_int($parsed) || $parsed < 0) {
                $this->error("A opcao --{$optionName} deve ser um inteiro maior ou igual a zero.");

                return null;
            }

            $plan[$planKey] = $parsed;
        }

        return $plan;
    }

    private function resolveProfile(): ?string
    {
        $profile = strtolower(trim((string) ($this->option('perfil') ?? self::PROFILE_CUSTOM)));
        $allowedProfiles = array_merge([self::PROFILE_CUSTOM], array_keys(self::PROFILES));

        if (! in_array($profile, $allowedProfiles, true)) {
            $this->error('Perfil invalido. Use: '.implode('|', $allowedProfiles).'.');

            return null;
        }

        return $profile;
    }

    /**
     * @return array{inserted:int, updated:int, deactivated:int}
     */
    private function applyProductChanges(int $toInsert, int $toUpdate, int $toDeactivate): array
    {
        $activeProducts = DB::table('produtos_base')
            ->where('prod_atv', 1)
            ->whereNotNull('prod_cod')
            ->whereRaw("TRIM(prod_cod) <> ''")
            ->orderBy('prod_id')
            ->get(['prod_id']);

        $productIdsToUpdate = $activeProducts
            ->take($toUpdate)
            ->pluck('prod_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $productIdsToDeactivate = $activeProducts
            ->slice(count($productIdsToUpdate))
            ->take($toDeactivate)
            ->pluck('prod_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        foreach ($productIdsToUpdate as $offset => $productId) {
            DB::table('produtos_base')
                ->where('prod_id', $productId)
                ->update([
                    'prod_nome' => sprintf('Produto Sync Atualizado %d', $productId),
                    'prod_cat' => $offset % 2 === 0 ? 'COMPONENTES' : 'PERIFERICOS',
                    'prod_subcat' => $offset % 2 === 0 ? 'ATUALIZADOS' : 'PROMOCAO',
                    'prod_desc' => sprintf('Atualizado pelo gerador de sync (%d)', $productId),
                    'prod_fab' => 'SYNC LABS',
                    'prod_mod' => sprintf('SYNC-MOD-%04d', $productId),
                    'prod_cor' => $offset % 2 === 0 ? 'PRETO' : 'BRANCO',
                    'prod_peso' => sprintf('%0.1fkg', 0.5 + ($offset * 0.1)),
                    'prod_larg' => sprintf('%0.1fcm', 20 + ($offset * 0.5)),
                    'prod_alt' => sprintf('%0.1fcm', 5 + ($offset * 0.3)),
                    'prod_prof' => sprintf('%0.1fcm', 10 + ($offset * 0.4)),
                    'prod_und' => 'UN',
                    'prod_dt_cad' => $this->sequenceDate($productId + $offset),
                ]);
        }

        if ($productIdsToDeactivate !== []) {
            DB::table('produtos_base')
                ->whereIn('prod_id', $productIdsToDeactivate)
                ->update(['prod_atv' => 0]);
        }

        $maxProductId = (int) (DB::table('produtos_base')->max('prod_id') ?? 0);

        for ($index = 0; $index < $toInsert; $index++) {
            $sequence = $maxProductId + $index + 1;

            DB::table('produtos_base')->insert([
                'prod_cod' => sprintf('SYNC%06d', $sequence),
                'prod_nome' => sprintf('Produto Sync %d', $sequence),
                'prod_cat' => $index % 2 === 0 ? 'COMPONENTES' : 'ACESSORIOS',
                'prod_subcat' => $index % 2 === 0 ? 'LOTES' : 'ATUALIZACOES',
                'prod_desc' => 'Produto criado para gerar delta de sincronizacao',
                'prod_fab' => 'SYNC LABS',
                'prod_mod' => sprintf('SYNC-NEW-%04d', $sequence),
                'prod_cor' => $index % 2 === 0 ? 'PRETO' : 'AZUL',
                'prod_peso' => sprintf('%0.1fkg', 0.8 + (($index % 10) * 0.1)),
                'prod_larg' => sprintf('%0.1fcm', 15 + (($index % 10) * 1.1)),
                'prod_alt' => sprintf('%0.1fcm', 4 + (($index % 8) * 0.8)),
                'prod_prof' => sprintf('%0.1fcm', 6 + (($index % 9) * 0.9)),
                'prod_und' => 'UN',
                'prod_atv' => 1,
                'prod_dt_cad' => $this->sequenceDate($sequence),
            ]);
        }

        return [
            'inserted' => $toInsert,
            'updated' => count($productIdsToUpdate),
            'deactivated' => count($productIdsToDeactivate),
        ];
    }

    /**
     * @return array{inserted:int, updated:int, deactivated:int}
     */
    private function applyPriceChanges(int $toInsert, int $toUpdate, int $toDeactivate): array
    {
        $syncVisiblePriceIds = DB::table('ve_precos')
            ->orderBy('preco_id')
            ->pluck('preco_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $priceIdsToUpdate = array_slice($syncVisiblePriceIds, 0, $toUpdate);
        $priceIdsToDeactivate = array_slice($syncVisiblePriceIds, count($priceIdsToUpdate), $toDeactivate);

        foreach ($priceIdsToUpdate as $offset => $priceId) {
            DB::table('precos_base')
                ->where('preco_id', $priceId)
                ->update([
                    'prc_valor' => $this->moneyText(350 + ($offset * 11.75)),
                    'prc_moeda' => $offset % 2 === 0 ? 'BRL' : 'USD',
                    'prc_desc' => $offset % 3 === 0 ? '5%' : '0',
                    'prc_acres' => $offset % 4 === 0 ? '1.5%' : '0',
                    'prc_promo' => $this->moneyText(320 + ($offset * 10.2)),
                    'prc_dt_ini_promo' => $this->sequenceDate($priceId + 1),
                    'prc_dt_fim_promo' => $this->sequenceDate($priceId + 11),
                    'prc_dt_atual' => $this->sequenceDate($priceId + 3),
                    'prc_origem' => 'SYNC JOB',
                    'prc_tipo_cli' => $offset % 2 === 0 ? 'VAREJO' : 'ATACADO',
                    'prc_vend_resp' => sprintf('Sync Bot %02d', ($offset % 99) + 1),
                    'prc_obs' => sprintf('Preco atualizado para gerar delta (%d)', $priceId),
                    'prc_status' => 'ativo',
                ]);
        }

        if ($priceIdsToDeactivate !== []) {
            DB::table('precos_base')
                ->whereIn('preco_id', $priceIdsToDeactivate)
                ->update([
                    'prc_status' => 'inativo',
                    'prc_obs' => 'Inativado pelo gerador de sync',
                ]);
        }

        $availableProductCodes = $this->activeProductCodesForSync();

        if ($availableProductCodes === []) {
            return [
                'inserted' => 0,
                'updated' => count($priceIdsToUpdate),
                'deactivated' => count($priceIdsToDeactivate),
            ];
        }

        for ($index = 0; $index < $toInsert; $index++) {
            $sequence = $index + 1;
            $productCode = $availableProductCodes[$index % count($availableProductCodes)];

            DB::table('precos_base')->insert([
                'prc_cod_prod' => $productCode,
                'prc_valor' => $this->moneyText(199.9 + ($index * 7.35)),
                'prc_moeda' => $index % 3 === 0 ? 'USD' : 'BRL',
                'prc_desc' => $index % 4 === 0 ? '10%' : '0',
                'prc_acres' => $index % 5 === 0 ? '2%' : '0',
                'prc_promo' => $index % 2 === 0 ? $this->moneyText(179.9 + ($index * 5.75)) : null,
                'prc_dt_ini_promo' => $index % 2 === 0 ? $this->sequenceDate(80 + $sequence) : null,
                'prc_dt_fim_promo' => $index % 2 === 0 ? $this->sequenceDate(90 + $sequence) : null,
                'prc_dt_atual' => $this->sequenceDate(70 + $sequence),
                'prc_origem' => 'SYNC JOB',
                'prc_tipo_cli' => $index % 2 === 0 ? 'VAREJO' : 'ATACADO',
                'prc_vend_resp' => sprintf('Gerador Sync %02d', ($index % 99) + 1),
                'prc_obs' => 'Preco novo para lote de sincronizacao',
                'prc_status' => 'ativo',
            ]);
        }

        return [
            'inserted' => $toInsert,
            'updated' => count($priceIdsToUpdate),
            'deactivated' => count($priceIdsToDeactivate),
        ];
    }

    /**
     * @return list<string>
     */
    private function activeProductCodesForSync(): array
    {
        return DB::table('produtos_base')
            ->selectRaw('UPPER(TRIM(prod_cod)) AS codigo_produto')
            ->where('prod_atv', 1)
            ->whereNotNull('prod_cod')
            ->whereRaw("TRIM(prod_cod) <> ''")
            ->groupByRaw('UPPER(TRIM(prod_cod))')
            ->orderBy('codigo_produto')
            ->pluck('codigo_produto')
            ->map(static fn ($value): string => (string) $value)
            ->all();
    }

    private function moneyText(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function sequenceDate(int $sequence): string
    {
        $dayOffset = $sequence % 365;

        return date('Y-m-d', strtotime("2026-01-01 +{$dayOffset} days"));
    }
}
