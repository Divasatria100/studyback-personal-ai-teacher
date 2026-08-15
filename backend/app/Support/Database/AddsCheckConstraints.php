<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Shared helper for adding named CHECK constraints on PostgreSQL.
 *
 * PostgreSQL is the target database (docs/04-database-design.md §7/§20).
 * SQLite (used only for the automated test suite) is not supported by the
 * Blueprint API for adding checks to an existing table, so it is skipped.
 */
trait AddsCheckConstraints
{
    /**
     * Add a named CHECK constraint to a table after it has been created.
     */
    protected function addCheckConstraint(string $table, string $name, string $expression): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
}
