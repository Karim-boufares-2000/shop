<?php

declare(strict_types=1);

namespace Tests\Support;

/** Fabrique de fichiers image (GD) pour les tests ; les fichiers sont créés dans le dossier temporaire. */
final class TestImages
{
    public static function available(): bool
    {
        return extension_loaded('gd') && class_exists(\finfo::class);
    }

    public static function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 20, 120, 200));
        $path = self::path('png');
        imagepng($image, $path);

        return $path;
    }

    public static function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 200, 60, 60));
        $path = self::path('jpg');
        imagejpeg($image, $path, 90);

        return $path;
    }

    public static function gif(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $path = self::path('gif');
        imagegif($image, $path);

        return $path;
    }

    public static function raw(string $content): string
    {
        $path = self::path('bin');
        file_put_contents($path, $content);

        return $path;
    }

    /** @return array{name:string,tmp_name:string,size:int,error:int} */
    public static function upload(string $path, string $name = 'photo.png', int $error = UPLOAD_ERR_OK): array
    {
        return ['name' => $name, 'tmp_name' => $path, 'size' => (int) @filesize($path), 'error' => $error];
    }

    private static function path(string $extension): string
    {
        return sys_get_temp_dir() . '/shop_test_' . bin2hex(random_bytes(6)) . '.' . $extension;
    }
}
