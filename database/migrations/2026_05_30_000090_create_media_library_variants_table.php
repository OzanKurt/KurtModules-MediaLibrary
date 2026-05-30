<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('media_library_items')->cascadeOnDelete();
            $table->string('key');
            $table->json('spec');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['item_id', 'key'], 'media_library_variants_item_key_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_variants');
    }
};
