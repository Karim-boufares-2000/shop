<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Traitement sécurisé des images téléversées.
 *  - Taille, type MIME RÉEL (finfo), cohérence getimagesize, dimensions et nombre de pixels contrôlés AVANT décodage.
 *  - L'image est décodée puis RÉ-ENCODÉE en JPEG (métadonnées EXIF et contenu parasite supprimés).
 *  - Noms de fichiers aléatoires générés côté serveur ; l'extension d'origine est ignorée.
 * Nécessite l'extension GD (et fileinfo).
 */
final class ImageProcessor
{
    public const MAX_BYTES = 5_242_880;
    public const MIN_SIDE = 100;
    public const MAX_SIDE = 6000;
    public const MAX_PIXELS = 12_000_000;
    public const FULL_SIDE = 1600;
    public const THUMB_SIDE = 400;

    private const ALLOWED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    /** @param bool $requireHttpUpload false uniquement dans les tests (fichier non issu de HTTP) */
    public function __construct(private string $directory, private bool $requireHttpUpload = true)
    {
    }

    /**
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return string identifiant aléatoire (32 hexadécimaux) ; fichiers {id}.jpg et {id}_t.jpg créés
     * @throws UploadException
     */
    public function store(array $file): string
    {
        $path = $this->validate($file);

        $data = file_get_contents($path);
        $source = $data === false ? false : @imagecreatefromstring($data);
        if ($source === false) {
            throw new UploadException('upload.invalid_type');
        }
        $source = $this->autoOrient($source, $path);

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new UploadException('upload.failed');
        }

        $id = bin2hex(random_bytes(16));
        $full = $this->directory . '/' . $id . '.jpg';
        $thumb = $this->directory . '/' . $id . '_t.jpg';
        try {
            $this->writeJpeg($source, self::FULL_SIDE, $full);
            $this->writeJpeg($source, self::THUMB_SIDE, $thumb);
        } catch (UploadException $e) {
            @unlink($full);
            @unlink($thumb);
            throw $e;
        }

        return $id;
    }

    /** Supprime les fichiers d'une image ; l'identifiant est validé avant toute opération sur le disque. */
    public function delete(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            return;
        }
        @unlink($this->directory . '/' . $id . '.jpg');
        @unlink($this->directory . '/' . $id . '_t.jpg');
    }

    /**
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return string chemin du fichier validé
     */
    private function validate(array $file): string
    {
        $error = $file['error'];
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new UploadException('upload.none');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new UploadException('upload.too_large');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException('upload.failed');
        }

        $path = $file['tmp_name'];
        if ($path === '' || !is_file($path) || ($this->requireHttpUpload && !is_uploaded_file($path))) {
            throw new UploadException('upload.failed');
        }
        $size = filesize($path);
        if ($size === false || $size === 0) {
            throw new UploadException('upload.invalid_type'); // fichier vide : pas une image
        }
        if ($size > self::MAX_BYTES) {
            throw new UploadException('upload.too_large');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            throw new UploadException('upload.invalid_type');
        }
        $info = @getimagesize($path);
        if ($info === false || $info[2] !== self::ALLOWED[$mime]) {
            throw new UploadException('upload.invalid_type');
        }
        [$width, $height] = $info;
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE
            || $width > self::MAX_SIDE || $height > self::MAX_SIDE
            || $width * $height > self::MAX_PIXELS) {
            throw new UploadException('upload.invalid_dimensions');
        }

        return $path;
    }

    private function autoOrient(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data') || (new \finfo(FILEINFO_MIME_TYPE))->file($path) !== 'image/jpeg') {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    private function writeJpeg(\GdImage $source, int $maxSide, string $target): void
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        if ($white === false) {
            throw new UploadException('upload.failed');
        }
        // Ces deux fonctions retournent toujours true : un échec lève une exception PHP, pas un false.
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        // imagejpeg() peut réellement échouer (disque plein, droits d'écriture) : contrôle conservé.
        if (!imagejpeg($canvas, $target, 85)) {
            throw new UploadException('upload.failed');
        }
    }
}
