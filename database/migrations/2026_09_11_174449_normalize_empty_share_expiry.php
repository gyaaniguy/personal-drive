<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shares')->where('expiry', '')->update(['expiry' => null]);
    }

    public function down(): void
    {
        // No-op: '' and null both mean "no expiry"; nothing to restore.
    }
};
