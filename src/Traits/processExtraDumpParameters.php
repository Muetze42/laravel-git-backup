<?php

namespace NormanHuth\LaravelGitBackup\Traits;

use Illuminate\Support\Str;
use Spatie\DbDumper\DbDumper;

/**
 * Source: https://github.com/spatie/laravel-backup \Spatie\Backup\Tasks\Backup\DbDumperFactory
 */
trait processExtraDumpParameters
{
    protected static function processExtraDumpParameters(array $dumpConfiguration, DbDumper $dbDumper): DbDumper
    {
        collect($dumpConfiguration)->each(function ($configValue, $configName) use ($dbDumper) {
            $methodName = lcfirst(Str::studly(is_numeric($configName) ? $configValue : $configName));
            $methodValue = is_numeric($configName) ? null : $configValue;

            $methodName = static::determineValidMethodName($dbDumper, $methodName);

            if (method_exists($dbDumper, $methodName)) {
                static::callMethodOnDumper($dbDumper, $methodName, $methodValue);
            }
        });

        return $dbDumper;
    }

    protected static function determineValidMethodName(DbDumper $dbDumper, string $methodName): string
    {
        return collect([$methodName, 'set'.ucfirst($methodName)])
            ->first(fn (string $methodName) => method_exists($dbDumper, $methodName), '');
    }

    protected static function callMethodOnDumper(DbDumper $dbDumper, string $methodName, $methodValue): DbDumper
    {
        if (! $methodValue) {
            $dbDumper->$methodName();

            return $dbDumper;
        }

        $dbDumper->$methodName($methodValue);

        return $dbDumper;
    }
}
