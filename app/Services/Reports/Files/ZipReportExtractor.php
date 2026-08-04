<?php

namespace App\Services\Reports\Files;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ZipReportExtractor
{
    /**
     * Extrae el único archivo TXT encontrado dentro del ZIP.
     *
     * @return array{
     *     original_zip: string,
     *     extracted_directory: string,
     *     txt_path: string,
     *     txt_name: string
     * }
     */
    public function extractTxt(string $zipPath): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException(
                'No se encontró el archivo ZIP seleccionado.'
            );
        }

        $zip = new ZipArchive();

        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            throw new RuntimeException(
                'El archivo seleccionado no es un ZIP válido.'
            );
        }

        $txtEntries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if (! is_string($entryName)) {
                continue;
            }

            if (
                ! str_ends_with($entryName, '/')
                && strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) === 'txt'
            ) {
                $txtEntries[] = $entryName;
            }
        }

        if ($txtEntries === []) {
            $zip->close();

            throw new RuntimeException(
                'El ZIP no contiene ningún archivo TXT.'
            );
        }

        if (count($txtEntries) > 1) {
            $zip->close();

            throw new RuntimeException(
                'El ZIP debe contener un único archivo TXT.'
            );
        }

        $temporaryDirectory = storage_path(
            'app/private/report-processing/' . Str::uuid()
        );

        if (
            ! is_dir($temporaryDirectory)
            && ! mkdir($temporaryDirectory, 0755, true)
            && ! is_dir($temporaryDirectory)
        ) {
            $zip->close();

            throw new RuntimeException(
                'No fue posible crear el directorio temporal.'
            );
        }

        $entryName = $txtEntries[0];

        if (! $zip->extractTo($temporaryDirectory, [$entryName])) {
            $zip->close();

            throw new RuntimeException(
                'No fue posible extraer el archivo TXT.'
            );
        }

        $zip->close();

        $txtPath = $temporaryDirectory
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $entryName);

        if (! is_file($txtPath)) {
            throw new RuntimeException(
                'El archivo TXT extraído no fue encontrado.'
            );
        }

        return [
            'original_zip' => $zipPath,
            'extracted_directory' => $temporaryDirectory,
            'txt_path' => $txtPath,
            'txt_name' => basename($entryName),
        ];
    }

    public function cleanup(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($directory);
    }
}