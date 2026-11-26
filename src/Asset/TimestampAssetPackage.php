<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\PackageInterface;

final readonly class TimestampAssetPackage implements PackageInterface
{
    public function __construct(
        private PackageInterface $inner,
    ) {
    }

    public function getVersion(string $path): string
    {
        return $this->inner->getVersion($path);
    }

    public function getUrl(string $path): string
    {
        $url = $this->inner->getUrl($path);

        $assetPath = parse_url($path, PHP_URL_PATH) ?: '';

        $extension = strtolower(
            pathinfo($assetPath, PATHINFO_EXTENSION)
        );

        if (!in_array($extension, ['css', 'js'], true)) {
            return $url;
        }

        // Only apply timestamps to physical public assets.
        $publicPath = dirname(__DIR__, 2)
            . '/public/'
            . ltrim($assetPath, '/');

        if (!is_file($publicPath)) {
            return $url;
        }

        $parts = explode('#', $url, 2);
        $separator = str_contains($parts[0], '?') ? '&' : '?';

        return $parts[0]
            . $separator
            . 't=' . time()
            . (isset($parts[1]) ? '#' . $parts[1] : '');
    }
}
