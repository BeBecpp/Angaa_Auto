<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates smaller web copies of the supplied brand PNGs (same artwork, alpha preserved, aspect ratio kept)
 * so pages do not download 1 MB originals. The supplied originals in public/brand stay untouched.
 */
#[Signature('brand:derivatives')]
#[Description('Build resized web copies and favicons from the supplied АНГАА АВТО brand PNGs')]
class BuildBrandDerivatives extends Command
{
    /**
     * @var array<string, list<int>> source => target widths
     */
    private const TARGETS = [
        'angaa-auto-logo-primary.png' => [320, 640],
        'angaa-auto-logo-reversed-navy.png' => [320, 640],
        'angaa-auto-mark.png' => [16, 32, 48, 96, 180, 192, 512],
        'angaa-auto-mascot-welcome.png' => [240, 480],
        'angaa-auto-mascot-success.png' => [240, 480],
    ];

    public function handle(): int
    {
        $sourceDir = public_path('brand');
        $targetDir = public_path('brand/web');

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach (self::TARGETS as $file => $widths) {
            $path = $sourceDir.DIRECTORY_SEPARATOR.$file;

            if (! is_file($path)) {
                $this->error("Missing {$file}. Run handoff/scripts/install-brand-assets first.");

                return self::FAILURE;
            }

            $source = imagecreatefrompng($path);
            imagesavealpha($source, true);
            $width = imagesx($source);
            $height = imagesy($source);

            foreach ($widths as $targetWidth) {
                $targetHeight = (int) round($height * $targetWidth / $width);
                $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                $name = pathinfo($file, PATHINFO_FILENAME)."-{$targetWidth}.png";
                imagepng($canvas, $targetDir.DIRECTORY_SEPARATOR.$name, 9);
                $this->line("  {$name} ({$targetWidth}×{$targetHeight})");
            }
        }

        copy($targetDir.DIRECTORY_SEPARATOR.'angaa-auto-mark-32.png', public_path('favicon.png'));
        $this->info('Brand web derivatives written to public/brand/web. Inspect favicons at 16/32px in a real browser.');

        return self::SUCCESS;
    }
}
