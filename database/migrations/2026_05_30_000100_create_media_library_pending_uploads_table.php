<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_pending_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->uuid('upload_id')->unique();
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('driver')->default('s3');
            $table->json('driver_payload');
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at'], 'media_library_pending_uploads_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_pending_uploads');
    }
};
