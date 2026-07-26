<?php

namespace App\Services\Informe202\Parsers;

use App\Services\Informe202\ProtegerErrorParser;
use RuntimeException;

class ErrorParserManager
{
    public function __construct(
        private ProtegerErrorParser $protegerParser,
        private DusakawiErrorParser $dusakawiParser
    ) {
    }

    public function parse(
        string $eps,
        string $filePath
    ): array {
        $parsers = [
            $this->protegerParser,
            $this->dusakawiParser,
        ];

        foreach ($parsers as $parser) {
            if ($parser->supports($eps)) {
                return $parser->parse(
                    $filePath
                );
            }
        }

        throw new RuntimeException(
            "No existe un lector configurado para la EPS {$eps}."
        );
    }
}