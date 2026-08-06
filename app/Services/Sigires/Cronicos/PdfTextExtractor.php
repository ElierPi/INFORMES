<?php

namespace App\Services\Sigires\Cronicos;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class PdfTextExtractor
{
    public function extract(string $pdfPath): string
    {
        if (! is_file($pdfPath)) {
            throw new RuntimeException('No se encontró la historia clínica en PDF.');
        }

        $configuredBinary = trim((string) config(
            'sigires_cronicos.pdf.pdftotext_path',
            'pdftotext'
        ));

        $configuredBinary = $this->resolveBinary($configuredBinary);

        if ($configuredBinary === '') {
            throw new RuntimeException(
                'No está configurada la ruta de pdftotext. '.
                'Define SIGIRES_PDFTOTEXT_PATH en el archivo .env.'
            );
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'sigires_pdf_');

        if (! is_string($temporaryPath) || $temporaryPath === '') {
            throw new RuntimeException(
                'No fue posible crear el archivo temporal para extraer el texto del PDF.'
            );
        }

        try {
            $process = new Process([
                $configuredBinary,
                '-layout',
                '-enc',
                'UTF-8',
                $pdfPath,
                $temporaryPath,
            ]);

            $process->setTimeout((float) config(
                'sigires_cronicos.pdf.timeout_seconds',
                60
            ));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'No fue posible leer el PDF con pdftotext. '.
                    trim($process->getErrorOutput() ?: $process->getOutput()).
                    ' Instala Poppler o configura SIGIRES_PDFTOTEXT_PATH con la ruta completa a pdftotext.exe.'
                );
            }

            $text = file_get_contents($temporaryPath);

            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException(
                    'El PDF no contiene texto extraíble. Debe revisarse manualmente o procesarse con OCR.'
                );
            }

            return $this->normalizeEncoding($text);
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException(
                'No fue posible extraer el texto de la historia clínica: '.
                $exception->getMessage(),
                previous: $exception
            );
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function resolveBinary(string $configuredBinary): string
    {
        if ($configuredBinary === '') {
            return '';
        }

        if (is_file($configuredBinary)) {
            return $configuredBinary;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                base_path('tools/poppler/Library/bin/pdftotext.exe'),
                base_path('tools/poppler/bin/pdftotext.exe'),
                'C:\\poppler\\Library\\bin\\pdftotext.exe',
                'C:\\poppler\\bin\\pdftotext.exe',
                'C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe',
                'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return $configuredBinary;
    }

    private function normalizeEncoding(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $converted = @mb_convert_encoding(
            $text,
            'UTF-8',
            ['Windows-1252', 'ISO-8859-1', 'UTF-8']
        );

        return is_string($converted) ? $converted : $text;
    }
}
