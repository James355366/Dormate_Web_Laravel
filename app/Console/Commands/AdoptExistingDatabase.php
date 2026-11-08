<?php

namespace App\Console\Commands;

use App\Services\DormateSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AdoptExistingDatabase extends Command
{
    protected $signature = 'dormate:adopt-existing';

    protected $description = 'Check the original DorMate tables and register the Laravel baseline without changing business records';

    public function handle(): int
    {
        $missing = [];
        foreach (DormateSchema::columns() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table.' (table missing)';

                continue;
            }
            foreach (array_diff($columns, Schema::getColumnListing($table)) as $column) {
                $missing[] = $table.'.'.$column;
            }
        }
        if ($missing) {
            $this->error('Schema does not match the supplied DorMate version. No data was changed.');
            foreach ($missing as $item) {
                $this->line($item);
            }

            return self::FAILURE;
        }
        $repository = app('migration.repository');
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
        if (! in_array(DormateSchema::MIGRATION, $repository->getRan(), true)) {
            $repository->log(DormateSchema::MIGRATION, $repository->getNextBatchNumber());
        }
        $this->info('Existing DorMate schema registered. Business tables and records were retained. Existing accounts can sign in.');

        $this->line('Run php artisan migrate to apply any pending feature updates.');

        return self::SUCCESS;
    }
}
