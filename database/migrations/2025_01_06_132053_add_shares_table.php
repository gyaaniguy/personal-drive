<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('password')->nullable();
            $table->timestamp('expiry')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->timestamp('last_accessed')->nullable();
        });

        Schema::create('shared_files', function (Blueprint $table) {
            $table->foreignId('share_id')->constrained('shares')->onDelete('cascade');
            $table->foreignId('file_id')->constrained('local_files')->onDelete('cascade');
            $table->primary(['share_id', 'file_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_files');
        Schema::dropIfExists('shares');
        Schema::dropIfExists('local_files_filename_public_path_unique');
    }
};
