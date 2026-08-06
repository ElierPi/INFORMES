<?php

namespace App\Support\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

trait HandlesTemporaryUploads
{
    /**
     * @param  array<int, string>  $extensions
     */
    protected function stabilizeUpload(
        mixed $upload,
        array $extensions,
        int $maxBytes,
        string $folder,
        string $baseName
    ): string {
        if (! $upload instanceof TemporaryUploadedFile) {
            throw new RuntimeException(
                'El archivo temporal ya no está disponible. Selecciónalo nuevamente.'
            );
        }

        $path = $upload->getRealPath();

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            throw new RuntimeException(
                'El archivo temporal expiró. Selecciónalo nuevamente.'
            );
        }

        $extension = strtolower($upload->getClientOriginalExtension());

        if (! in_array($extension, $extensions, true)) {
            throw new RuntimeException(
                'Formato no permitido. Se acepta: '.implode(', ', $extensions).'.'
            );
        }

        $size = filesize($path);

        if ($size === false || $size > $maxBytes) {
            throw new RuntimeException(
                'El archivo supera el tamaño permitido.'
            );
        }

        Storage::disk('local')->makeDirectory($folder);
        $relativePath = trim($folder, '/').'/'.$baseName.'.'.$extension;
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! copy($path, $absolutePath)) {
            throw new RuntimeException(
                'No fue posible copiar el archivo a una ubicación estable.'
            );
        }

        return $absolutePath;
    }
}
