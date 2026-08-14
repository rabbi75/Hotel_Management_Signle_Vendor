<?php

declare(strict_types=1);

namespace App\Install\Services;

class PermissionsChecker
{
    /**
     * @return list<array{path: string, ok: bool, detail: string}>
     */
    public function check(): array
    {
        $rows = [];

        foreach ((array) config('installer.writable.base_path', []) as $relative) {
            $absolute = base_path((string) $relative);
            $rows[] = $this->probe($absolute, (string) $relative);
        }

        foreach ((array) config('installer.writable.directories', []) as $relative) {
            $absolute = base_path((string) $relative);
            $rows[] = $this->probeDirectory($absolute, (string) $relative);
        }

        return $rows;
    }

    public function passes(): bool
    {
        foreach ($this->check() as $row) {
            if (! $row['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{path: string, ok: bool, detail: string}
     */
    protected function probe(string $absolute, string $label): array
    {
        if (! file_exists($absolute)) {
            $dir = dirname($absolute);

            if (! is_dir($dir) || ! is_writable($dir)) {
                return [
                    'path' => $label,
                    'ok' => false,
                    'detail' => 'Parent directory is not writable. Create the file or chmod the folder.',
                ];
            }

            $ok = @file_put_contents($absolute, '') !== false;

            return [
                'path' => $label,
                'ok' => $ok,
                'detail' => $ok ? 'Writable' : 'Cannot create file',
            ];
        }

        $ok = is_writable($absolute);

        return [
            'path' => $label,
            'ok' => $ok,
            'detail' => $ok ? 'Writable' : 'Not writable — chmod 644/775 and ensure the web user owns the file',
        ];
    }

    /**
     * @return array{path: string, ok: bool, detail: string}
     */
    protected function probeDirectory(string $absolute, string $label): array
    {
        if (! is_dir($absolute) && ! @mkdir($absolute, 0755, true) && ! is_dir($absolute)) {
            return [
                'path' => $label,
                'ok' => false,
                'detail' => 'Directory missing and could not be created',
            ];
        }

        $probe = rtrim($absolute, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.installer_write_test';

        $written = @file_put_contents($probe, 'ok') !== false;
        if ($written) {
            @unlink($probe);
        }

        return [
            'path' => $label,
            'ok' => $written,
            'detail' => $written ? 'Writable' : 'Not writable — chmod 775 and chown to the web server user',
        ];
    }
}
