<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductPriceCache
{
    private const VERSION_KEY = 'api:produtos-precos:version';
    private const INDEX_PREFIX = 'api:produtos-precos:index';

    public static function keyForIndex(string $queryString): string
    {
        return sprintf(
            '%s:%s:%s',
            self::INDEX_PREFIX,
            self::version(),
            sha1($queryString)
        );
    }

    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    private static function version(): string
    {
        $version = Cache::get(self::VERSION_KEY);

        if (is_string($version) && $version !== '') {
            return $version;
        }

        $version = (string) Str::uuid();
        Cache::forever(self::VERSION_KEY, $version);

        return $version;
    }
}
