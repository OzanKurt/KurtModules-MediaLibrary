<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_folders', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('parent_id')->nullable()->constrained('media_library_folders')->restrictOnDelete();
            $table->string('slug');
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('path')->index();
            $table->unsignedInteger('depth')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->string('visibility')->default('private');
            $table->unsignedBigInteger('item_count')->default(0);
            $table->unsignedBigInteger('descendant_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_type', 'owner_id', 'parent_id', 'slug'], 'media_library_folders_owner_parent_slug_uq');
            $table->index(['owner_type', 'owner_id', 'path'], 'media_library_folders_owner_path_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_folders');
    }
};
