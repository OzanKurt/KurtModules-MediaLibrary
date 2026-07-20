<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkResolver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class ShareLinkController
{
    public function __construct(
        private readonly ShareLinkResolver $resolver,
        private readonly AccessLogger $logger,
    ) {}

    public function show(string $token, Request $request): Response
    {
        $link = $this->resolver->resolve($token);
        $abilities = $link->abilities;
        $requested = $request->query('download') !== null ? 'download' : 'view';

        if (! in_array($requested, $abilities, true)) {
            abort(403, 'ability_not_granted');
        }

        $link->forceFill([
            'access_count' => $link->access_count + 1,
            'last_accessed_at' => now(),
            'last_accessed_ip' => $request->ip(),
        ])->save();

        /** @var Model|null $user */
        $user = $request->user();

        $this->logger->log($link->item, $link, $user, AccessAction::from($requested));

        $media = $link->item?->spatieMedia();
        if ($media === null) {
            abort(410, 'media_gone');
        }

        $diskName = (string) $media->disk;
        $isDownload = $requested === 'download';

        // Local disks expose an absolute filesystem path we can hand straight
        // to Symfony's file/download responses. Remote disks (s3, etc.) have no
        // such path, so stream the object through the disk's own response()/
        // download() helpers instead of calling getPath() (which throws).
        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            $path = $media->getPath();

            return $isDownload
                ? response()->download($path, $media->file_name, [], ResponseHeaderBag::DISPOSITION_ATTACHMENT)
                : response()->file($path);
        }

        $relativePath = $media->getPathRelativeToRoot();
        $filesystem = Storage::disk($diskName);

        return $isDownload
            ? $filesystem->download($relativePath, $media->file_name)
            : $filesystem->response($relativePath, $media->file_name);
    }
}
