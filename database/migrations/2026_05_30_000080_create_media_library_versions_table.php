<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('media_library_items')->cascadeOnDelete();
            $table->unsignedBigInteger('spatie_media_id');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size');
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_versions');
    }
};
