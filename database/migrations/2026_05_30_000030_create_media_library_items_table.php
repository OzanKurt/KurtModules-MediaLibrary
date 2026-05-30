<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_items', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('folder_id')->nullable()->constrained('media_library_folders')->nullOnDelete();
            $table->foreignId('storage_id')->constrained('media_library_storage')->cascadeOnDelete();
            $table->string('slug');
            $table->json('title');
            $table->json('alt_text')->nullable();
            $table->json('caption')->nullable();
            $table->json('description')->nullable();
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 8, 3)->nullable();
            $table->decimal('focal_x', 4, 3)->default(0.500);
            $table->decimal('focal_y', 4, 3)->default(0.500);
            $table->char('dominant_color', 7)->nullable();
            $table->json('palette')->nullable();
            $table->string('blurhash')->nullable();
            $table->json('exif')->nullable();
            $table->json('ai_tags')->nullable();
            $table->text('extracted_text')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_type', 'owner_id', 'slug'], 'media_library_items_owner_slug_uq');
            $table->index(['owner_type', 'owner_id', 'folder_id'], 'media_library_items_owner_folder_idx');
            $table->index('mime_type', 'media_library_items_mime_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_items');
    }
};
