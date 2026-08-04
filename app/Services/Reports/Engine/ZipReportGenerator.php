<?php

namespace App\Services\Reports\Engine;

use RuntimeException;
use ZipArchive;

final class ZipReportGenerator
{
    public function generate(
        string $sourceFile,
        string $zipPath,
        ?string $entryName = null,
    ): string {
        if (! is_file($sourceFile)) {
            throw new RuntimeException('No se encontró el archivo que será incluido en el ZIP.');
        }

        $directory = dirname($zipPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No fue posible crear el directorio {$directory}.");
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException("No fue posible crear el ZIP. Código: {$opened}.");
        }

        $added = $zip->addFile($sourceFile, $entryName ?: basename($sourceFile));

        if (! $added) {
            $zip->close();
            throw new RuntimeException('No fue posible agregar el TXT al ZIP.');
        }

        $zip->close();

        if (! is_file($zipPath)) {
            throw new RuntimeException('El ZIP terminó de generarse, pero no fue encontrado.');
        }

        return $zipPath;
    }
}
