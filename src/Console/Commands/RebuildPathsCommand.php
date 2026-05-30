<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

final class RebuildPathsCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:rebuild-paths {owner?}';

    /** @var string */
    protected $description = 'Recompute folder path + depth by walking the parent chain.';

    public function handle(): int
    {
        $query = MediaLibraryFolder::query()->whereNull('parent_id');

        if ($this->argument('owner')) {
            $query->where('owner_id', $this->argument('owner'));
        }

        $roots = $query->get();

        $rebuilt = 0;
        foreach ($roots as $root) {
            $rebuilt += $this->rebuildSubtree($root, '', 0);
        }

        $this->info("Rebuilt paths for {$rebuilt} folder(s).");

        return self::SUCCESS;
    }

    private function rebuildSubtree(MediaLibraryFolder $folder, string $parentPath, int $depth): int
    {
        $folder->forceFill([
            'path' => $parentPath.'/'.$folder->slug,
            'depth' => $depth,
        ])->save();

        $count = 1;
        foreach ($folder->children as $child) {
            $count += $this->rebuildSubtree($child, $folder->path, $depth + 1);
        }

        return $count;
    }
}
