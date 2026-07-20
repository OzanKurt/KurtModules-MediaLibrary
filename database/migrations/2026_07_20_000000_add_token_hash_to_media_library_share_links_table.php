<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library_share_links', function (Blueprint $table): void {
            // Lookups now happen by SHA-256 of the token so the raw bearer
            // credential is never used as a query key. Indexed for O(1) resolve.
            $table->string('token_hash', 64)->nullable()->after('token')->index();
        });

        // Backfill existing rows so links minted before this migration keep
        // resolving through the hashed path (no plaintext fallback required).
        DB::table('media_library_share_links')
            ->select(['id', 'token'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('media_library_share_links')
                        ->where('id', $row->id)
                        ->update(['token_hash' => hash('sha256', (string) $row->token)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('media_library_share_links', function (Blueprint $table): void {
            $table->dropIndex(['token_hash']);
            $table->dropColumn('token_hash');
        });
    }
};
