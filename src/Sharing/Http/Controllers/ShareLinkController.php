<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkResolver;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class ShareLinkController
{
    public function __construct(
        private readonly ShareLinkResolver $resolver,
        private readonly AccessLogger $logger,
    ) {}

    public function show(string $token, Request $request): BinaryFileResponse
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

        $path = $media->getPath();

        return $requested === 'download'
            ? response()->download($path, $media->file_name, [], ResponseHeaderBag::DISPOSITION_ATTACHMENT)
            : response()->file($path);
    }
}
