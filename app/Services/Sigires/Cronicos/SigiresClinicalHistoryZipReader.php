<?php

namespace App\Services\Sigires\Cronicos;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SigiresClinicalHistoryZipReader
{
    public function __construct(
        private readonly PdfTextExtractor $textExtractor
    ) {
    }

    /**
     * @return array{
     *     histories: array<int, array{
     *         file_name: string,
     *         document_type: ?string,
     *         document_number: string,
     *         text: string
     *     }>,
     *     warnings: array<int, string>
     * }
     */
    public function read(string $zipPath): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('No se encontró el ZIP de historias clínicas.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'La extensión ZIP de PHP no está habilitada.'
            );
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);

        if ($openResult !== true) {
            throw new RuntimeException(
                'No fue posible abrir el ZIP de historias clínicas.'
            );
        }

        $directory = storage_path(
            'app/private/sigires-cronicos/extracted/'.Str::uuid()
        );

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $zip->close();

            throw new RuntimeException(
                'No fue posible crear la carpeta temporal de historias clínicas.'
            );
        }

        $histories = [];
        $warnings = [];
        $pdfEntries = 0;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $entryName = is_array($stat)
                    ? (string) ($stat['name'] ?? '')
                    : '';

                if ($entryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') {
                    continue;
                }

                $pdfEntries++;

                $safeName = basename(str_replace('\\', '/', $entryName));
                $targetPath = $directory.DIRECTORY_SEPARATOR.$safeName;
                $stream = $zip->getStream($entryName);

                if (! is_resource($stream)) {
                    $warnings[] = "No se pudo extraer {$safeName}.";

                    continue;
                }

                $output = fopen($targetPath, 'wb');

                if (! is_resource($output)) {
                    fclose($stream);
                    $warnings[] = "No se pudo crear el archivo temporal {$safeName}.";

                    continue;
                }

                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);

                [$documentType, $documentNumber] = $this->identifyDocument(
                    $safeName
                );

                if ($documentNumber === '') {
                    $warnings[] =
                        "No se pudo identificar el documento desde el nombre {$safeName}.";

                    continue;
                }

                try {
                    $text = $this->textExtractor->extract($targetPath);
                } catch (RuntimeException $exception) {
                    $warnings[] = "{$safeName}: {$exception->getMessage()}";

                    continue;
                }

                $histories[] = [
                    'file_name' => $safeName,
                    'document_type' => $documentType,
                    'document_number' => $documentNumber,
                    'text' => $text,
                ];
            }
        } finally {
            $zip->close();
            $this->removeDirectory($directory);
        }

        if ($histories === []) {
            if ($pdfEntries === 0) {
                throw new RuntimeException(
                    'El ZIP no contiene archivos PDF.'
                );
            }

            $details = array_slice($warnings, 0, 3);
            $message = 'Se encontraron '.$pdfEntries.' archivos PDF, pero ninguno pudo procesarse.';

            if ($details !== []) {
                $message .= ' Motivos: '.implode(' | ', $details);
            }

            throw new RuntimeException($message);
        }

        return [
            'histories' => $histories,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function identifyDocument(string $fileName): array
    {
        $types = 'RC|TI|CC|CE|PA|MS|AS|CD|SC|PE|PT|SI|DE|CN';

        if (preg_match(
            '/(?:^|[^A-Z])('.$types.')\s*([0-9A-Z]{3,20})(?:[^0-9A-Z]|$)/i',
            $fileName,
            $matches
        ) === 1) {
            return [
                mb_strtoupper($matches[1]),
                $this->normalizeDocumentNumber($matches[2]),
            ];
        }

        if (preg_match('/([0-9]{5,20})/', $fileName, $matches) === 1) {
            return [null, $this->normalizeDocumentNumber($matches[1])];
        }

        return [null, ''];
    }

    private function normalizeDocumentNumber(string $value): string
    {
        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            mb_strtoupper(trim($value))
        ) ?? '';
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
