<?php

namespace App\Support\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageUploadService
{
    public function store(UploadedFile $file, string $directory, string $prefix = ''): string
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Image must be PNG, JPEG, or WEBP.');
        }

        if (($file->getSize() ?? 0) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Image must be 5MB or smaller.');
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'png'));
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $extension = 'png';
        }

        $directory = trim($directory, '/');
        $filename = $prefix.Str::lower((string) Str::uuid()).'.'.$extension;
        $targetDirectory = public_path('uploads/'.$directory);

        if (! is_dir($targetDirectory) && ! @mkdir($targetDirectory, 0755, true) && ! is_dir($targetDirectory)) {
            throw new \RuntimeException('Unable to create upload directory.');
        }

        $file->move($targetDirectory, $filename);

        return '/uploads/'.$directory.'/'.$filename;
    }
}
