<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_folder_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('media_library_folders')->cascadeOnDelete();
            $table->string('subject_type');
            $table->string('subject_value')->nullable();
            $table->string('capability');
            $table->boolean('cascade')->default(true);
            $table->timestamps();

            $table->index(['folder_id', 'subject_type', 'subject_value'], 'media_library_folder_permissions_folder_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_folder_permissions');
    }
};
