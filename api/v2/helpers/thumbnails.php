<?php

declare(strict_types=1);

/**
 * Gera thumbnails de imagens (small: 300w, medium: 800w).
 * Salva ao lado do original com sufixo _sm e _md.
 * Retorna array com caminhos relativos dos thumbnails gerados.
 */
function generateThumbnails(string $originalPath, string $mime): array
{
    $imageLoaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
        'image/bmp'  => 'imagecreatefrombmp',
    ];

    if (!isset($imageLoaders[$mime]) || !is_file($originalPath)) {
        return [];
    }

    $loader = $imageLoaders[$mime];
    $src = @$loader($originalPath);
    if ($src === false) {
        return [];
    }

    $srcWidth = imagesx($src);
    $srcHeight = imagesy($src);

    $sizes = [
        'small' => 300,
        'medium' => 800,
    ];

    $generated = [];
    $pathInfo = pathinfo($originalPath);
    $dir = $pathInfo['dirname'];
    $filename = $pathInfo['filename'];

    foreach ($sizes as $label => $maxWidth) {
        if ($srcWidth <= $maxWidth) {
            continue;
        }

        $ratio = $maxWidth / $srcWidth;
        $newHeight = (int)round($srcHeight * $ratio);
        $thumb = imagecreatetruecolor($maxWidth, $newHeight);

        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefilledrectangle($thumb, 0, 0, $maxWidth - 1, $newHeight - 1, $transparent);
        }

        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $maxWidth, $newHeight, $srcWidth, $srcHeight);

        $suffix = $label === 'small' ? '_sm' : '_md';
        $thumbPath = $dir . '/' . $filename . $suffix . '.' . ($pathInfo['extension'] ?? 'jpg');

        $quality = ($mime === 'image/jpeg') ? 80 : -1;
        $saved = false;
        if ($mime === 'image/jpeg') {
            $saved = @imagejpeg($thumb, $thumbPath, $quality);
        } elseif ($mime === 'image/png') {
            $saved = @imagepng($thumb, $thumbPath, 6);
        } elseif ($mime === 'image/gif') {
            $saved = @imagegif($thumb, $thumbPath);
        } elseif ($mime === 'image/webp') {
            $saved = @imagewebp($thumb, $thumbPath, 80);
        } elseif ($mime === 'image/bmp') {
            $saved = @imagebmp($thumb, $thumbPath);
        }

        @imagedestroy($thumb);

        if ($saved && is_file($thumbPath)) {
            $generated[$label] = $thumbPath;
        }
    }

    @imagedestroy($src);
    return $generated;
}
