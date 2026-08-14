<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

/**
 * The catalogue of events other modules may publish to webhook subscribers.
 *
 * Registered as a container singleton so a module's service provider can add
 * its own events at boot; an event that is not registered can neither be
 * subscribed to in the UI nor dispatched.
 */
class WebhookEventRegistry
{
    /** @var array<string, array{name: string, group: string, description: string}> */
    protected array $events = [];

    public function register(string $name, string $group, string $description = ''): static
    {
        $this->events[$name] = ['name' => $name, 'group' => $group, 'description' => $description];

        return $this;
    }

    /**
     * @param  array<string, string>  $events  Event name => description.
     */
    public function registerMany(string $group, array $events): static
    {
        foreach ($events as $name => $description) {
            $this->register($name, $group, $description);
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->events);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->events);
    }

    /**
     * @return list<array{name: string, group: string, description: string}>
     */
    public function all(): array
    {
        $events = array_values($this->events);

        usort($events, static fn (array $a, array $b): int => [$a['group'], $a['name']] <=> [$b['group'], $b['name']]);

        return $events;
    }

    /**
     * @return array<string, list<array{name: string, group: string, description: string}>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $event) {
            $grouped[$event['group']][] = $event;
        }

        return $grouped;
    }
}
