<?php

namespace daacreators\CreatorsTicketing\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class UserColumnNullability
{
    public static function change(string $table, bool $nullable): void
    {
        $column = collect(Schema::getColumns($table))->firstWhere('name', 'user_id');
        if (! $column) {
            throw new RuntimeException("Missing user_id column on {$table}.");
        }
        if (! $nullable && DB::table($table)->whereNull('user_id')->exists()) {
            throw new RuntimeException("Cannot roll back guest support while {$table} contains records without a user. Assign or archive those records first.");
        }

        $grammar = DB::connection()->getQueryGrammar();
        $wrappedTable = $grammar->wrapTable($table);
        $wrappedColumn = $grammar->wrap('user_id');
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Preserve the actual type, collation and foreign-key actions, including custom IDs.
            $foreignKeys = array_filter(Schema::getForeignKeys($table), fn ($key) => in_array('user_id', $key['columns'], true));
            foreach ($foreignKeys as $key) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($key['name']));
            }
            try {
                $collation = $column['collation'] ? ' COLLATE '.$grammar->wrap($column['collation']) : '';
                DB::statement("ALTER TABLE {$wrappedTable} MODIFY {$wrappedColumn} {$column['type']}{$collation} ".($nullable ? 'NULL' : 'NOT NULL'));
            } finally {
                foreach ($foreignKeys as $key) {
                    Schema::table($table, function (Blueprint $blueprint) use ($key) {
                        $blueprint->foreign($key['columns'], $key['name'])
                            ->references($key['foreign_columns'])->on($key['foreign_table'])
                            ->onUpdate($key['on_update'])->onDelete($key['on_delete']);
                    });
                }
            }

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$wrappedTable} ALTER COLUMN {$wrappedColumn} ".($nullable ? 'DROP' : 'SET').' NOT NULL');

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException("Guest user column migration is not supported for database driver {$driver}.");
        }

        $type = match (strtolower($column['type_name'])) {
            'integer', 'bigint', 'int' => 'bigInteger',
            'varchar', 'nvarchar' => 'string',
            'char', 'nchar' => 'char',
            'text' => 'text',
            default => throw new RuntimeException("Unsupported user_id column type {$column['type_name']}."),
        };
        preg_match('/\((\d+)\)/', $column['type'], $length);
        Schema::table($table, function (Blueprint $blueprint) use ($type, $nullable, $length) {
            $blueprint->addColumn($type, 'user_id', ['length' => (int) ($length[1] ?? 255)])
                ->nullable($nullable)->change();
        });
    }
}
