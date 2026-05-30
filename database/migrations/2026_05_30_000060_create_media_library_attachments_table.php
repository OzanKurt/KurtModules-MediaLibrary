<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('media_library_items')->cascadeOnDelete();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('role')->default('attachment');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id', 'role'], 'media_library_attachments_attachable_role_idx');
            $table->index('item_id', 'media_library_attachments_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_attachments');
    }
};
