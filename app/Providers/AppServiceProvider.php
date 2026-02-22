<?php

namespace App\Providers;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
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
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $this->registerSqliteNormalizeDateFunction($event->connection);
            $this->registerSqliteNormalizeMoneyFunction($event->connection);
        });
    }

    private function registerSqliteNormalizeDateFunction(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();

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

    private function registerSqliteNormalizeMoneyFunction(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();

        $pdo->sqliteCreateFunction('normalize_money', static function ($value): ?float {
            if ($value === null) {
                return null;
            }

            $normalized = preg_replace('/\s+/u', '', (string) $value);

            if ($normalized === null || $normalized === '') {
                return null;
            }

            if (! preg_match('/\d/', $normalized)) {
                return null;
            }

            $filtered = preg_replace('/[^0-9,.\-]/u', '', $normalized);

            if ($filtered === null || $filtered === '' || ! preg_match('/\d/', $filtered)) {
                return null;
            }

            $isNegative = str_starts_with($filtered, '-');
            $filtered = ltrim($filtered, '-');
            $filtered = str_replace('-', '', $filtered);

            $lastComma = strrpos($filtered, ',');
            $lastDot = strrpos($filtered, '.');

            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    $filtered = str_replace('.', '', $filtered);
                    $filtered = str_replace(',', '.', $filtered);
                } else {
                    $filtered = str_replace(',', '', $filtered);
                }
            } elseif ($lastComma !== false) {
                $filtered = str_replace(',', '.', $filtered);
            }

            if (substr_count($filtered, '.') > 1) {
                $parts = explode('.', $filtered);
                $decimalPart = array_pop($parts);
                $filtered = implode('', $parts).'.'.$decimalPart;
            }

            if ($filtered === '' || ! preg_match('/^\d+(\.\d+)?$/', $filtered)) {
                return null;
            }

            $numericValue = (float) $filtered;

            return $isNegative ? -$numericValue : $numericValue;
        }, 1);
    }
}
