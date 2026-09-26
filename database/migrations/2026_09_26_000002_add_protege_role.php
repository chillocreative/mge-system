<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'Protege', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        // Keep the role on rollback if users have been assigned to it.
        $role = Role::query()->where('name', 'Protege')->where('guard_name', 'web')->first();

        if ($role && $role->users()->doesntExist()) {
            $role->delete();
        }
    }
};
