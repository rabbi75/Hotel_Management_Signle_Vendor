<?php

declare(strict_types=1);

namespace App\Install\Services;

class RequirementsChecker
{
    /**
     * @return list<array{label: string, ok: bool, required: bool, detail: string}>
     */
    public function check(): array
    {
        $min = (string) config('installer.php.min', '8.2.0');
        $rows = [];

        $rows[] = [
            'label' => 'PHP version',
            'ok' => version_compare(PHP_VERSION, $min, '>='),
            'required' => true,
            'detail' => PHP_VERSION.' (requires >= '.$min.')',
        ];

        foreach ((array) config('installer.php.extensions', []) as $extension) {
            $rows[] = [
                'label' => 'ext-'.$extension,
                'ok' => extension_loaded((string) $extension),
                'required' => true,
                'detail' => extension_loaded((string) $extension) ? 'Loaded' : 'Missing',
            ];
        }

        $imageOk = false;
        foreach ((array) config('installer.php.image_extensions', []) as $extension) {
            if (extension_loaded((string) $extension)) {
                $imageOk = true;
                break;
            }
        }

        $rows[] = [
            'label' => 'Image processing (gd or imagick)',
            'ok' => $imageOk,
            'required' => true,
            'detail' => $imageOk ? 'Available' : 'Install gd or imagick',
        ];

        $rows[] = [
            'label' => 'exec()',
            'ok' => function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
            'required' => false,
            'detail' => 'Recommended for artisan migrate during install',
        ];

        $rows[] = [
            'label' => 'memory_limit',
            'ok' => $this->memoryAtLeast(256),
            'required' => false,
            'detail' => (string) ini_get('memory_limit'),
        ];

        return $rows;
    }

    public function passes(): bool
    {
        foreach ($this->check() as $row) {
            if ($row['required'] && ! $row['ok']) {
                return false;
            }
        }

        return true;
    }

    protected function memoryAtLeast(int $megabytes): bool
    {
        $limit = (string) ini_get('memory_limit');

        if ($limit === '-1') {
            return true;
        }

        $bytes = $this->toBytes($limit);

        return $bytes >= $megabytes * 1024 * 1024;
    }

    protected function toBytes(string $value): int
    {
        $value = trim($value);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }
}
