<?php
declare(strict_types=1);

/** Bildverarbeitung mit GD (Upload prüfen, drehen, verkleinern, neu kodieren). */
final class Images
{
    private const MAX_PIXELS = 70_000_000;

    /** Längste Seite der Anzeige-Version und der Vorschau (Pixel). */
    public const FULL_SIZE = 1600;
    public const THUMB_SIZE = 400;

    /**
     * Prüft und lädt ein hochgeladenes Bild.
     * @return array{0: ?GdImage, 1: ?string, 2: int} [Bild, Fehlermeldung, IMAGETYPE_*]
     */
    public static function load(string $tmpFile, bool $applyOrientation = false): array
    {
        $info = @getimagesize($tmpFile);
        if ($info === false) {
            return [null, 'Die Datei ist kein gültiges Bild.', 0];
        }
        [$w, $h, $type] = $info;
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELS) {
            return [null, 'Das Bild hat eine zu hohe Auflösung.', $type];
        }
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return [null, 'Bildformat nicht unterstützt (erlaubt: JPEG, PNG, WebP).', $type];
        }
        if (!self::ensureMemory($w, $h)) {
            return [null, 'Das Bild ist für den Server zu groß (' . $w . ' × ' . $h . ' Pixel). Bitte mit kleinerer Auflösung aufnehmen oder vorher verkleinern.', $type];
        }
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpFile),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmpFile),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpFile) : false,
            default        => false,
        };
        if ($img === false) {
            return [null, 'Das Bild konnte nicht gelesen werden (erlaubt: JPEG, PNG, WebP).', $type];
        }
        if ($applyOrientation && $type === IMAGETYPE_JPEG) {
            $img = self::applyExifOrientation($img, $tmpFile);
        }
        return [$img, null, $type];
    }

    /** Fotos vom Handy sind oft nur per EXIF-Hinweis gedreht. GD ignoriert den Hinweis, daher drehen wir selbst. */
    public static function applyExifOrientation(GdImage $img, string $file): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($file);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => 0,
        };
        if ($angle !== 0) {
            $rotated = @imagerotate($img, $angle, 0);
            if ($rotated !== false) {
                return $rotated;
            }
        }
        return $img;
    }

    /** Verkleinert proportional auf hoechstens $maxW x $maxH; vergroessert nie. Transparenz bleibt erhalten. */
    public static function fit(GdImage $img, int $maxW, int $maxH): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min($maxW / $w, $maxH / $h, 1.0);
        if ($scale >= 1.0) {
            return $img;
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $out;
    }

    /** Legt ein Bild mit Transparenz auf weißen Grund (für JPEG). */
    public static function flatten(GdImage $img): GdImage
    {
        $out = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagealphablending($out, true);
        imagecopy($out, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        return $out;
    }

    public static function savePng(GdImage $img, string $path): bool
    {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        return imagepng($img, $path, 6);
    }

    public static function saveJpeg(GdImage $img, string $path, int $quality = 82): bool
    {
        return imagejpeg($img, $path, $quality);
    }

    /**
     * Speichert ein hochgeladenes Foto als Anzeige-Version und Vorschau (beides JPEG, neu kodiert).
     * @return array{0: ?string, 1: ?string} [Dateiname, Fehlermeldung]
     */
    public static function storePhoto(string $tmpFile): array
    {
        [$img, $err, $type] = self::load($tmpFile, true);
        if ($img === null) {
            return [null, $err];
        }
        if ($type !== IMAGETYPE_JPEG) {
            $img = self::flatten($img);
        }
        $full = self::fit($img, self::FULL_SIZE, self::FULL_SIZE);
        $thumb = self::fit($img, self::THUMB_SIZE, self::THUMB_SIZE);

        foreach (['images', 'thumbs'] as $dir) {
            if (!is_dir(STORAGE . '/' . $dir) && !@mkdir(STORAGE . '/' . $dir, 0755, true)) {
                return [null, 'Der Ordner storage/' . $dir . ' fehlt oder ist nicht beschreibbar.'];
            }
        }
        $name = bin2hex(random_bytes(12)) . '.jpg';
        if (!self::saveJpeg($full, STORAGE . '/images/' . $name, 82) || !self::saveJpeg($thumb, STORAGE . '/thumbs/' . $name, 80)) {
            @unlink(STORAGE . '/images/' . $name);
            @unlink(STORAGE . '/thumbs/' . $name);
            return [null, 'Das Bild konnte nicht gespeichert werden (Schreibrechte für storage/ prüfen).'];
        }
        return [$name, null];
    }

    /** Liefert ein gespeichertes Foto (thumb|full) mit Zwischenspeicher-Kopfzeilen aus. @return bool false, wenn die Datei fehlt */
    public static function serve(string $name, string $size): bool
    {
        $file = STORAGE . '/' . ($size === 'thumb' ? 'thumbs' : 'images') . '/' . basename($name);
        if (!in_array($size, ['thumb', 'full'], true) || !is_file($file)) {
            return false;
        }
        $etag = '"' . md5((string) filemtime($file) . ':' . (string) filesize($file)) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=604800');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return true;
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
        return true;
    }

    public static function deletePhoto(string $name): void
    {
        $name = basename($name);
        if ($name !== '') {
            @unlink(STORAGE . '/images/' . $name);
            @unlink(STORAGE . '/thumbs/' . $name);
        }
    }

    // ---- Arbeitsspeicher --------------------------------------------------------

    /** Stellt sicher, dass genug PHP-Speicher für das Dekodieren eines Bildes zur Verfügung steht (soweit der Hoster es erlaubt). */
    public static function ensureMemory(int $w, int $h): bool
    {
        $need = $w * $h * 8 + 24 * 1024 * 1024; // Original + gedrehte Kopie + Reserve
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit < 0) {
            return true;
        }
        if ($limit - memory_get_usage(true) >= $need) {
            return true;
        }
        @ini_set('memory_limit', (string) ((int) ceil((memory_get_usage(true) + $need) / 1048576) + 16) . 'M');
        $limit = self::bytes((string) ini_get('memory_limit'));
        return $limit < 0 || $limit - memory_get_usage(true) >= $need;
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '-1') {
            return -1;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g'     => $n * 1073741824,
            'm'     => $n * 1048576,
            'k'     => $n * 1024,
            default => $n,
        };
    }
}
