<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_library_item_tag', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('media_library_items')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('media_library_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['item_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_library_item_tag');
    }
};
