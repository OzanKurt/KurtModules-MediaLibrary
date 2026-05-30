<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained('media_library_items')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('media_library_folders')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->json('abilities');
            $table->string('invitee_email')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('last_accessed_ip')->nullable();
            $table->foreignId('created_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_share_links');
    }
};
