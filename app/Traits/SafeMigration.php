<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

trait SafeMigration
{
    /**
     * Create a table only if it does not exist.
     *
     * @param string $table
     * @param Closure $callback
     */
    public function safeCreateTable(string $table, Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }

    /**
     * Add a column only if it does not exist.
     *
     * @param string $table
     * @param string $column
     * @param Closure $callback
     */
    public function safeAddColumn(string $table, string $column, Closure $callback): void
    {
        if (!Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $table) use ($callback) {
                $callback($table);
            });
        }
    }

    /**
     * Drop a column only if it exists.
     *
     * @param string $table
     * @param array|string $columns
     */
    public function safeDropColumn(string $table, array|string $columns): void
    {
        Schema::table($table, function (Blueprint $table) use ($columns) {
            foreach ((array)$columns as $column) {
                if (Schema::hasColumn($table->getTable(), $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Drop a table only if it exists.
     *
     * @param string $table
     */
    public function safeDropTable(string $table): void
    {
        if (Schema::hasTable($table)) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Add multiple columns only if they do not exist.
     *
     * @param string $table
     * @param array $columnsWithDefinitions
     */
    public function safeAddColumns(string $table, array $columnsWithDefinitions): void
    {
        Schema::table($table, function (Blueprint $table) use ($columnsWithDefinitions) {
            foreach ($columnsWithDefinitions as $column => $definition) {
                if (!Schema::hasColumn($table->getTable(), $column)) {
                    $definition($table);
                }
            }
        });
    }

    /**
     * Drop multiple columns only if they exist.
     *
     * @param string $table
     * @param array $columns
     */
    public function safeDropColumns(string $table, array $columns): void
    {
        Schema::table($table, function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table->getTable(), $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Safely add a foreign key constraint if it does not already exist.
     *
     * @param string $table
     * @param string $column
     * @param string $foreignTable
     * @param string $foreignColumn
     * @param string|null $constraintName (optional) - Custom name for the foreign key constraint
     * @param string $onDelete
     */
    public function safeAddForeignKey(
        string $table,
        string $column,
        string $foreignTable,
        string $foreignColumn = 'id',
        ?string $constraintName = null,
        string $onDelete = 'cascade'
    ): void {
        $constraintName = $constraintName ?? "{$table}_{$column}_fk";
        $indexName = "{$table}_{$column}_index";

        if (!$this->foreignKeyExists($table, $constraintName)) {
            // ✅ Add index only if it doesn't exist
            if (!$this->indexExists($table, $indexName)) {
                Schema::table($table, function (Blueprint $table) use ($column, $indexName) {
                    $table->index($column, $indexName);
                });
            }

            // ✅ Add foreign key
            Schema::table($table, function (Blueprint $table) use ($column, $foreignTable, $foreignColumn, $constraintName, $onDelete) {
                $table->foreign($column, $constraintName)
                    ->references($foreignColumn)
                    ->on($foreignTable)
                    ->onDelete($onDelete);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::selectOne("
        SELECT COUNT(*) as count
        FROM information_schema.STATISTICS
        WHERE table_schema = ?
        AND table_name = ?
        AND index_name = ?
    ", [$database, $table, $indexName]);

        return $result->count > 0;
    }

    /**
     * Safely drop a foreign key constraint if it exists.
     *
     * @param string $table
     * @param string $constraintName
     */
    public function safeDropForeignKey(string $table, string $constraintName): void
    {
        if ($this->foreignKeyExists($table, $constraintName)) {
            Schema::table($table, function (Blueprint $table) use ($constraintName) {
                $table->dropForeign($constraintName);
            });
        }
    }

    /**
     * Check if a foreign key exists in a table.
     *
     * @param string $table
     * @param string $constraintName
     * @return bool
     */
    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        return (bool)DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ?", [$table, $constraintName]);
    }

    /**
     * Safely add morphs with a specified type if they do not exist.
     *
     * @param string $table
     * @param string $columnPrefix (e.g., 'messageable')
     * @param string $idType (Default: 'unsignedBigInteger', Can be 'string' for UUIDs or WhatsApp IDs)
     */
    public function safeAddMorphs(string $table, string $columnPrefix, string $idType = 'unsignedBigInteger'): void
    {
        Schema::table($table, function (Blueprint $table) use ($columnPrefix, $idType) {
            if (!Schema::hasColumn($table->getTable(), "{$columnPrefix}_id")) {
                if ($idType === 'string') {
                    $table->string("{$columnPrefix}_id", 255);
                } else {
                    $table->unsignedBigInteger("{$columnPrefix}_id");
                }
            }

            if (!Schema::hasColumn($table->getTable(), "{$columnPrefix}_type")) {
                $table->string("{$columnPrefix}_type");
            }
        });
    }

    /**
     * Safely drop morphs if they exist.
     *
     * @param string $table
     * @param string $columnPrefix (e.g., 'messageable')
     */
    public function safeDropMorphs(string $table, string $columnPrefix): void
    {
        Schema::table($table, function (Blueprint $table) use ($columnPrefix) {
            if (Schema::hasColumn($table->getTable(), "{$columnPrefix}_id")) {
                $table->dropColumn("{$columnPrefix}_id");
            }
            if (Schema::hasColumn($table->getTable(), "{$columnPrefix}_type")) {
                $table->dropColumn("{$columnPrefix}_type");
            }
        });
    }

    /**
     * Change a column type if it exists, or create it if it does not exist.
     *
     * @param string $table
     * @param string $column
     * @param \Closure $changeCallback
     * @param \Closure $createCallback
     */
    public function safeChangeOrCreateColumn(string $table, string $column, \Closure $changeCallback, \Closure $createCallback): void
    {
        Schema::table($table, function (Blueprint $table) use ($column, $changeCallback, $createCallback) {
            if (Schema::hasColumn($table->getTable(), $column)) {
                $changeCallback($table); // Change column if it exists
            } else {
                $createCallback($table); // Create column if it does not exist
            }
        });
    }

}
