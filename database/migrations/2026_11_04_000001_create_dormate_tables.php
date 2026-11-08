<?php

use App\Services\DormateSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (array_keys(DormateSchema::columns()) as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('Existing table '.$table.' found. To retain your existing DorMate data, use php artisan dormate:adopt-existing. For a fresh install choose an empty database.');
            }
        }
        foreach (DormateSchema::statements() as $sql) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Automatic rollback is disabled for the existing DorMate schema to retain user data. Use a separate disposable database when experimenting.');
    }
};
