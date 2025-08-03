<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insert([
            [
                'key' => 'storage_path', 'value' => '', 'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};
