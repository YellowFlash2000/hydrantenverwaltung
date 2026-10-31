<?php
declare(strict_types=1);

/** Liefert das einstellbare Farbschema und das hochgeladene Logo aus. */
final class BrandingController extends Controller
{
    public function themeCss(): void
    {
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: public, max-age=31536000, immutable');
        echo Theme::css();
    }

    public function logo(): void
    {
        $name = Settings::get('logo_file');
        $file = STORAGE . '/branding/' . basename($name);
        if ($name === '' || !is_file($file)) {
            $this->fail(404, 'Nicht gefunden', 'Es ist kein Logo hinterlegt.');
            return;
        }
        header('Content-Type: image/png');
        header('Content-Length: ' . (string) filesize($file));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($file);
    }
}
