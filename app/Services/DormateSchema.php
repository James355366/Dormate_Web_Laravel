<?php

namespace App\Services;

class DormateSchema
{
    public const MIGRATION = '2026_11_04_000001_create_dormate_tables';

    public static function statements(): array
    {
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(database_path('schema.sql')));

        return array_values(array_filter(array_map('trim', explode(';', $sql)), fn ($statement) => $statement !== '' && ! preg_match('/^(CREATE DATABASE|USE)\b/i', $statement)));
    }

    public static function columns(): array
    {
        $tables = [];
        foreach (self::statements() as $sql) {
            if (! preg_match('/^CREATE TABLE (\w+) \((.*)\) ENGINE/s', $sql, $m)) {
                continue;
            }
            preg_match_all('/^\s*(\w+) (?:INT|BIGINT|SMALLINT|TINYINT|VARCHAR|CHAR|DECIMAL|DATE|DATETIME|ENUM|TEXT)\b/m', $m[2], $columns);
            $tables[$m[1]] = $columns[1];
        }

        return $tables;
    }
}
