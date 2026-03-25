<?php

namespace daacreators\CreatorsTicketing\Support;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;

final class UserForeignKey
{
    /**
     * Add a user foreign key column that matches the configured user ID type.
     *
     * Supports int/bigint, uuid, ulid, and custom string/char IDs (e.g. nanoid).
     */
    public static function add(Blueprint $table, string $column = 'user_id', bool $nullable = false, string $onDelete = 'cascade'): void
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);

        if (!is_string($userModel) || $userModel === '' || !class_exists($userModel)) {
            $userModel = \App\Models\User::class;
        }

        /** @var \Illuminate\Database\Eloquent\Model $userInstance */
        $userInstance = new $userModel;

        $userTable = config('creators-ticketing.user_table') ?: $userInstance->getTable();
        $userKey = config('creators-ticketing.user_key') ?: $userInstance->getKeyName();

        $defaultConfig = config('creators-ticketing.user_key_column', []);
        $columnConfig = config("creators-ticketing.user_foreign_keys.$column", []);

        $defaultConfig = is_array($defaultConfig) ? $defaultConfig : [];
        $columnConfig = is_array($columnConfig) ? $columnConfig : [];

        $columnConfig = array_merge($defaultConfig, $columnConfig);

        $type = $columnConfig['type'] ?? null;
        $length = $columnConfig['length'] ?? null;
        $collation = $columnConfig['collation'] ?? null;

        if ($type === null) {
            $type = self::inferType($userInstance);
        }

        $type = strtolower((string) $type);

        $foreignDefinition = match ($type) {
            'int', 'bigint' => self::addForeignId($table, $column, $nullable, $userTable, $userKey),
            'uuid' => self::addForeignUuid($table, $column, $nullable, $userTable, $userKey),
            'ulid' => self::addForeignUlid($table, $column, $nullable, $userTable, $userKey, $length),
            'string' => self::addForeignString($table, $column, $nullable, $userTable, $userKey, $length, $collation),
            'char' => self::addForeignChar($table, $column, $nullable, $userTable, $userKey, $length, $collation),
            default => throw new InvalidArgumentException("Unsupported user key type [$type] for column [$column]."),
        };

        match (strtolower($onDelete)) {
            'cascade' => $foreignDefinition->cascadeOnDelete(),
            'null', 'set null', 'set_null' => $foreignDefinition->nullOnDelete(),
            'restrict' => $foreignDefinition->restrictOnDelete(),
            'no action', 'no_action' => $foreignDefinition->noActionOnDelete(),
            default => null,
        };
    }

    private static function inferType(Model $userInstance): string
    {
        if ($userInstance->getKeyType() === 'int') {
            return 'int';
        }

        $traits = class_uses_recursive($userInstance) ?: [];

        if (in_array(HasUlids::class, $traits, true)) {
            return 'ulid';
        }

        return 'uuid';
    }

    private static function addForeignId(Blueprint $table, string $column, bool $nullable, string $userTable, string $userKey)
    {
        $definition = $table->foreignId($column);

        if ($nullable) {
            $definition->nullable();
        }

        return $definition->constrained($userTable, $userKey);
    }

    private static function addForeignUuid(Blueprint $table, string $column, bool $nullable, string $userTable, string $userKey)
    {
        $definition = $table->foreignUuid($column);

        if ($nullable) {
            $definition->nullable();
        }

        return $definition->constrained($userTable, $userKey);
    }

    private static function addForeignUlid(Blueprint $table, string $column, bool $nullable, string $userTable, string $userKey, $length)
    {
        $definition = $table->foreignUlid($column, is_numeric($length) ? (int) $length : 26);

        if ($nullable) {
            $definition->nullable();
        }

        return $definition->constrained($userTable, $userKey);
    }

    private static function addForeignString(Blueprint $table, string $column, bool $nullable, string $userTable, string $userKey, $length, $collation)
    {
        $definition = $table->string($column, is_numeric($length) ? (int) $length : 255);

        if (is_string($collation) && $collation !== '') {
            $definition->collation($collation);
        }

        if ($nullable) {
            $definition->nullable();
        }

        return $table->foreign($column)->references($userKey)->on($userTable);
    }

    private static function addForeignChar(Blueprint $table, string $column, bool $nullable, string $userTable, string $userKey, $length, $collation)
    {
        $definition = $table->char($column, is_numeric($length) ? (int) $length : 36);

        if (is_string($collation) && $collation !== '') {
            $definition->collation($collation);
        }

        if ($nullable) {
            $definition->nullable();
        }

        return $table->foreign($column)->references($userKey)->on($userTable);
    }
}

