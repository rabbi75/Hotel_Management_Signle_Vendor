<?php

declare(strict_types=1);

namespace App\Install\Support;

use Illuminate\Support\Facades\Session;

class InstallerState
{
    public const SESSION_KEY = 'installer';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        $state = $this->all();
        data_set($state, $key, $value);
        Session::put(self::SESSION_KEY, $state);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function merge(array $values): void
    {
        Session::put(self::SESSION_KEY, array_replace_recursive($this->all(), $values));
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function markStep(string $step): void
    {
        $completed = $this->get('completed', []);
        if (! is_array($completed)) {
            $completed = [];
        }
        $completed[$step] = true;
        $this->put('completed', $completed);
    }

    public function stepCompleted(string $step): bool
    {
        return (bool) $this->get("completed.{$step}", false);
    }

    public function canAccess(string $step): bool
    {
        $steps = (array) config('installer.steps', []);
        $index = array_search($step, $steps, true);

        if ($index === false) {
            return false;
        }

        if ($index === 0) {
            return true;
        }

        for ($i = 0; $i < $index; $i++) {
            if (! $this->stepCompleted((string) $steps[$i])) {
                return false;
            }
        }

        return true;
    }
}
