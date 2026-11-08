<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('No demo records inserted. Create your first account with php artisan dormate:create-admin.');
    }
}
