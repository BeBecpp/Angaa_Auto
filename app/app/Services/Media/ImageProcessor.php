<?php

namespace App\Services\Media;

use App\Support\DomainException;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validates an uploaded photo by actually decoding it, bounds its pixel count, applies EXIF
 * orientation, strips metadata by re-encoding, and stores a normalized evidentiary JPEG plus a
 * thumbnail under random private paths. Originals are not retained.
 *
 * HEIC/HEIF is rejected with conversion advice: this runtime has no tested HEIC decoder.
 */
class ImageProcessor
{
    /**
     * @return array{disk: string, private_path: string, preview_path: string, mime: string, byte_size: int, width: int, height: int, checksum: string}
     */
    public function store(UploadedFile $file): array
    {
        $image = $this->decode($file);

        return $this->persist($image);
    }

    /**
     * Persist a GD image that the application generated itself (demo placeholders).
     *
     * @return array{disk: string, private_path: string, preview_path: string, mime: string, byte_size: int, width: int, height: int, checksum: string}
     */
    public function persist(GdImage $image): array
    {
        $image = $this->fitWithin($image, (int) config('angaa.uploads.max_edge_px'));
        $thumb = $this->fitWithin($image, (int) config('angaa.uploads.thumb_edge_px'), copy: true);

        $mainBytes = $this->encodeJpeg($image, 85);
        $thumbBytes = $this->encodeJpeg($thumb, 78);

        $disk = (string) config('angaa.uploads.disk');
        $folder = now()->format('Y/m');
        $name = Str::lower((string) Str::ulid()).Str::random(8);
        $mainPath = "{$folder}/{$name}.jpg";
        $thumbPath = "{$folder}/{$name}_t.jpg";

        Storage::disk($disk)->put($mainPath, $mainBytes);
        Storage::disk($disk)->put($thumbPath, $thumbBytes);

        return [
            'disk' => $disk,
            'private_path' => $mainPath,
            'preview_path' => $thumbPath,
            'mime' => 'image/jpeg',
            'byte_size' => strlen($mainBytes),
            'width' => imagesx($image),
            'height' => imagesy($image),
            'checksum' => hash('sha256', $mainBytes),
        ];
    }

    public function decode(UploadedFile $file): GdImage
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            throw new DomainException('Зураг уншигдсангүй. Дахин сонгоно уу.');
        }

        if ($file->getSize() > (int) config('angaa.uploads.max_bytes')) {
            throw new DomainException('Зураг 8 MB-аас ихгүй байх ёстой.');
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        $detectedMime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (in_array($extension, ['heic', 'heif'], true) || in_array($detectedMime, ['image/heic', 'image/heif'], true)) {
            throw new DomainException('HEIC зургийг одоогоор хүлээн авах боломжгүй. Утасныхаа камерын тохиргоог «Most Compatible / JPEG» болгох эсвэл дэлгэцийн зураг (screenshot) илгээнэ үү.');
        }

        if (! in_array($detectedMime, config('angaa.uploads.allowed_mimes'), true)) {
            throw new DomainException('Зөвхөн JPEG, PNG, WebP зураг илгээнэ үү.');
        }

        $info = @getimagesize($path);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new DomainException('Зургийн файл гэмтсэн эсвэл зураг биш байна.');
        }

        if ((int) config('angaa.uploads.max_pixels') < $info[0] * $info[1]) {
            throw new DomainException('Зургийн хэмжээ хэт том байна (16 мегапикселээс ихгүй).');
        }

        $image = match ($detectedMime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new DomainException('Зургийг задлах боломжгүй байна. Өөр зураг сонгоно уу.');
        }

        if ($detectedMime === 'image/jpeg') {
            $image = $this->applyExifOrientation($image, $path);
        }

        return $image;
    }

    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function fitWithin(GdImage $image, int $maxEdge, bool $copy = false): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxEdge / max($width, $height));

        if ($scale >= 1 && ! $copy) {
            return $this->flatten($image);
        }

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function flatten(GdImage $image): GdImage
    {
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $canvas;
    }

    private function encodeJpeg(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
