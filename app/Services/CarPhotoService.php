<?php

namespace App\Services;

use App\Models\Car;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Re-encodes uploaded car photos to strip metadata (EXIF/GPS) and produce
 * a consistent web-sized image plus a thumbnail. Validation of the upload
 * itself (mime, size, dimensions) happens in the form request.
 */
class CarPhotoService
{
    private const MAX_LONG_EDGE = 1600;

    private const THUMB_LONG_EDGE = 640;

    private const QUALITY = 80;

    /** Re-encodes the upload, stores it on the `public` disk, removes any previous photo. */
    public function store(Car $car, UploadedFile $file): void
    {
        $image = $this->readImage($file);

        $full = $this->resize($image, self::MAX_LONG_EDGE);
        $thumb = $this->resize($image, self::THUMB_LONG_EDGE);

        $dir = "cars/{$car->event_id}";
        $random = Str::random(10);
        $photoPath = "{$dir}/{$car->id}-{$random}.webp";
        $thumbPath = "{$dir}/{$car->id}-{$random}-thumb.webp";

        Storage::disk('public')->put($photoPath, $this->encodeWebp($full));
        Storage::disk('public')->put($thumbPath, $this->encodeWebp($thumb));

        imagedestroy($image);
        imagedestroy($full);
        imagedestroy($thumb);

        $this->delete($car);

        $car->photo_path = $photoPath;
        $car->thumb_path = $thumbPath;
        $car->save();
    }

    /** Removes the current photo files (if any) and clears the columns. */
    public function delete(Car $car): void
    {
        $disk = Storage::disk('public');
        if ($car->photo_path && $disk->exists($car->photo_path)) {
            $disk->delete($car->photo_path);
        }
        if ($car->thumb_path && $disk->exists($car->thumb_path)) {
            $disk->delete($car->thumb_path);
        }
    }

    /** @return \GdImage */
    private function readImage(UploadedFile $file)
    {
        $data = file_get_contents($file->getRealPath());
        $image = imagecreatefromstring($data);
        abort_if($image === false, 422, 'That image could not be read.');

        $mime = $file->getMimeType();
        if (in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            $image = $this->applyOrientation($image, $file->getRealPath());
        }

        return $image;
    }

    /** @param  \GdImage  $image */
    private function applyOrientation($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    /** @param  \GdImage  $image
     * @return \GdImage */
    private function resize($image, int $longEdge)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $longEdge / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    /** @param  \GdImage  $image */
    private function encodeWebp($image): string
    {
        ob_start();
        imagewebp($image, null, self::QUALITY);

        return ob_get_clean();
    }
}
