<?php

namespace App\Services\Gestantes;

use RuntimeException;
use ZipArchive;

class GestantesZipGenerator
{
    public function generate(
        string $txtPath,
        string $zipPath
    ): string {
        if (! is_file($txtPath)) {
            throw new RuntimeException(
                'No se encontró el TXT que se debe comprimir.'
            );
        }

        $directory = dirname($zipPath);

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'No fue posible crear la carpeta del ZIP.'
            );
        }

        $zip = new ZipArchive();

        $result = $zip->open(
            $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        );

        if ($result !== true) {
            throw new RuntimeException(
                'No fue posible crear el archivo ZIP.'
            );
        }

        $added = $zip->addFile(
            $txtPath,
            basename($txtPath)
        );

        if (! $added) {
            $zip->close();

            throw new RuntimeException(
                'No fue posible agregar el TXT al archivo ZIP.'
            );
        }

        $zip->close();

        if (
            ! is_file($zipPath)
            || filesize($zipPath) === 0
        ) {
            throw new RuntimeException(
                'El archivo ZIP no fue generado correctamente.'
            );
        }

        return $zipPath;
    }
}