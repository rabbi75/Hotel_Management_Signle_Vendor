<?php

declare(strict_types=1);

namespace App\Install\Services;

use Illuminate\Support\Str;

class EnvWriter
{
    public function __construct(protected ?string $envPath = null) {}

    public function path(): string
    {
        return $this->envPath ?? base_path('.env');
    }

    public function ensureExists(): void
    {
        if (is_file($this->path())) {
            return;
        }

        $example = base_path('.env.example');

        if (is_file($example)) {
            copy($example, $this->path());

            return;
        }

        file_put_contents($this->path(), "APP_NAME=\"Hotel Management\"\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://localhost\n");
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    public function setMany(array $values): void
    {
        $this->ensureExists();

        $contents = (string) file_get_contents($this->path());

        foreach ($values as $key => $value) {
            $contents = $this->replaceKey($contents, (string) $key, $this->stringify($value));
        }

        file_put_contents($this->path(), $contents);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $this->ensureExists();

        $contents = (string) file_get_contents($this->path());

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return $default;
        }

        return trim($matches[1], " \t\"'");
    }

    protected function replaceKey(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$value;

        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $contents) === 1) {
            return (string) preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $contents);
        }

        return rtrim($contents)."\n".$line."\n";
    }

    protected function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $string = (string) $value;

        if ($string === '') {
            return '';
        }

        if (preg_match('/\s|#|"|\'/', $string) === 1 || Str::contains($string, ['=', '$'])) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $string).'"';
        }

        return $string;
    }
}
