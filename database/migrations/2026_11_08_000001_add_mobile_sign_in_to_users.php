<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing contact numbers are deliberately not enrolled as login identities.
        // They may be shared, duplicated, or belong to a guardian.
        if (! Schema::hasColumn('users', 'mobile_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('mobile_number', 16)->nullable()->unique('uq_users_mobile_number');
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 190)->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback would remove mobile sign-in identities. Restore a database backup only when intentionally reverting this update.');
    }
};
