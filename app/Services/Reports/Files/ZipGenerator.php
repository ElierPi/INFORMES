<?php

namespace App\Services\Reports\Files;

use RuntimeException;
use ZipArchive;

class ZipGenerator
{
    public function generate(string $txtPath, string $zipPath): string
    {
        if (! is_file($txtPath)) {
            throw new RuntimeException(
                'No se encontró el TXT que será incluido en el ZIP.'
            );
        }

        $directory = dirname($zipPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                "No fue posible crear el directorio {$directory}."
            );
        }

        $zip = new ZipArchive();
        $result = $zip->open(
            $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        );

        if ($result !== true) {
            throw new RuntimeException(
                "No fue posible crear el ZIP. Código: {$result}."
            );
        }

        $zip->addFile($txtPath, basename($txtPath));
        $zip->close();

        if (! is_file($zipPath)) {
            throw new RuntimeException(
                'El ZIP terminó de generarse, pero no fue encontrado.'
            );
        }

        return $zipPath;
    }
}
