<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class ImageOptimizer
{
    /**
     * @return array{contents: string, extension: string, mime_type: string, size: int}
     */
    public function optimize(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new RuntimeException('The uploaded image could not be decoded.');
        }

        try {
            $image = $this->resize($image);
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);

            ob_start();
            $written = imagewebp($image, null, (int) config('media.image_quality', 82));
            $optimized = ob_get_clean();

            if (! $written || ! is_string($optimized)) {
                throw new RuntimeException('The uploaded image could not be optimized.');
            }

            return [
                'contents' => $optimized,
                'extension' => 'webp',
                'mime_type' => 'image/webp',
                'size' => strlen($optimized),
            ];
        } finally {
            imagedestroy($image);
        }
    }

    private function resize(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = (int) config('media.max_image_dimension', 2560);

        if (max($width, $height) <= $maxDimension) {
            return $image;
        }

        $scale = $maxDimension / max($width, $height);
        $resized = imagescale(
            $image,
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
            IMG_BICUBIC_FIXED,
        );

        if ($resized === false) {
            throw new RuntimeException('The uploaded image could not be resized.');
        }

        imagedestroy($image);

        return $resized;
    }
}
