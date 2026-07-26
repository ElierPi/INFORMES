<?php

namespace App\Services\Informe202\Parsers;

interface ErrorParserInterface
{
    public function parse(string $filePath): array;

    public function supports(string $eps): bool;
}