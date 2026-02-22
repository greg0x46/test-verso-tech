<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerSqliteNormalizeDateFunction();
    }

    private function registerSqliteNormalizeDateFunction(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        $pdo->sqliteCreateFunction('normalize_date', static function ($value) {
            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);

            if ($value === '') {
                return null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }

            if (preg_match('/^\d{4}[\/.]\d{2}[\/.]\d{2}$/', $value)) {
                return str_replace(['/', '.'], '-', $value);
            }

            if (preg_match('/^\d{2}[-\/.]\d{2}[-\/.]\d{4}$/', $value)) {
                $parts = preg_split('/[-\/.]/', $value);
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }

            return null;
        }, 1);
    }
}
