<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Loads the phpMyAdmin camsys dump into the LOCAL SQLite camsys copy. Refuses to touch a MySQL camsys. */
#[Signature('camsys:import-dump {path=database/camsys/qapi_registration_mdl.sql}')]
#[Description('Import the camsys sample dump into the local SQLite camsys database')]
class ImportCamsysDump extends Command
{
    public function handle(): int
    {
        $connection = DB::connection('camsys');
        if ($connection->getDriverName() !== 'sqlite') {
            $this->error('camsys:import-dump only writes to a local SQLite copy, never the real camsys.');

            return self::FAILURE;
        }

        $path = base_path($this->argument('path'));
        $dump = is_file($path) ? (string) file_get_contents($path) : '';
        if (! preg_match('/INSERT INTO `qapi_registration_mdl` \(([^)]+)\) VALUES.+?\);$/ms', $dump, $match)) {
            $this->error("No qapi_registration_mdl INSERT found in {$path}.");

            return self::FAILURE;
        }

        $database = $connection->getConfig('database');
        if ($database !== ':memory:' && ! is_file($database)) {
            touch($database);
        }

        // camsys' generated columns are MySQL-only, so recreate the table with plain columns and reuse the INSERT.
        $columns = array_map(fn (string $c) => trim($c, ' `'), explode(',', $match[1]));
        $definitions = array_map(
            fn (string $c) => "\"{$c}\" ".(in_array($c, ['YEAR_ENTRY', 'INTAKE_NUMBER'], true) ? 'INTEGER' : 'TEXT'),
            $columns,
        );
        $connection->statement('DROP TABLE IF EXISTS qapi_registration_mdl');
        $connection->statement('CREATE TABLE qapi_registration_mdl ('.implode(', ', $definitions).')');
        $connection->unprepared($match[0]);

        $this->info('Imported '.$connection->table('qapi_registration_mdl')->count().' camsys rows.');

        return self::SUCCESS;
    }
}
