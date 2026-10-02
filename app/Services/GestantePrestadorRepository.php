<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class GestantePrestadorRepository
{
    private const FILE = 'gestante-mensual/prestadores.json';
    private const DEFAULTS = [
        ['name' => 'Wayuu Anashii', 'code' => '444300063502'],
    ];

    public function all(): array
    {
        $this->ensureDefaults();
        $items = json_decode(Storage::disk('local')->get(self::FILE), true);
        if (!is_array($items)) {
            $items = self::DEFAULTS;
            $this->write($items);
        }
        usort($items, fn (array $a, array $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
        return array_values($items);
    }

    public function add(string $name, string $code): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $code = preg_replace('/\D+/', '', $code);
        if ($name === '') throw new \InvalidArgumentException('El nombre de la IPS/prestador es obligatorio.');
        if (strlen($code) !== 12) throw new \InvalidArgumentException('El código de habilitación debe tener exactamente 12 dígitos.');

        $items = $this->all();
        foreach ($items as $item) {
            if (($item['code'] ?? '') === $code) {
                if (strcasecmp($item['name'] ?? '', $name) === 0) return $item;
                throw new \InvalidArgumentException('El código '.$code.' ya está guardado para '.($item['name'] ?? 'otro prestador').'.');
            }
        }

        $item = ['name' => $name, 'code' => $code];
        $items[] = $item;
        $this->write($items);
        return $item;
    }

    public function findByCode(string $code): ?array
    {
        foreach ($this->all() as $item) {
            if (($item['code'] ?? '') === $code) return $item;
        }
        return null;
    }

    private function ensureDefaults(): void
    {
        if (!Storage::disk('local')->exists(self::FILE)) {
            $this->write(self::DEFAULTS);
            return;
        }
        $items = json_decode(Storage::disk('local')->get(self::FILE), true);
        if (!is_array($items)) {
            $this->write(self::DEFAULTS);
            return;
        }
        $hasWayuu = collect($items)->contains(fn ($item) => ($item['code'] ?? '') === '444300063502');
        if (!$hasWayuu) {
            $items[] = self::DEFAULTS[0];
            $this->write($items);
        }
    }

    private function write(array $items): void
    {
        Storage::disk('local')->put(self::FILE, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
