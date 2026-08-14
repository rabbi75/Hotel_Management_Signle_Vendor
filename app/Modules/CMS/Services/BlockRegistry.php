<?php

declare(strict_types=1);

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Blocks\BlockSchema;
use Illuminate\Support\Facades\File;
use ReflectionClass;

/**
 * Resolves block types to their schema classes.
 *
 * Discovery is by directory scan rather than by a hand-maintained map: dropping
 * a new {@see BlockSchema} into app/Modules/CMS/Blocks is all it takes to make
 * a block available. `saas.cms.block_types` only decides the order the picker
 * offers them in — an unlisted class still works, it just sorts last.
 */
class BlockRegistry
{
    /** @var array<string, BlockSchema>|null */
    protected ?array $schemas = null;

    /**
     * @var list<class-string<BlockSchema>>
     */
    protected array $extra = [];

    /**
     * @param  class-string<BlockSchema>  $schema
     */
    public function register(string $schema): void
    {
        $this->extra[] = $schema;
        $this->schemas = null;
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->all());
    }

    public function get(string $type): ?BlockSchema
    {
        return $this->all()[$type] ?? null;
    }

    /**
     * @return array<string, BlockSchema>
     */
    public function all(): array
    {
        return $this->schemas ??= $this->discover();
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->all());
    }

    /**
     * The payload the React editor renders its add-block picker and per-block
     * forms from.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(
            static fn (BlockSchema $schema): array => $schema->toArray(),
            $this->all(),
        ));
    }

    /**
     * @return array<string, BlockSchema>
     */
    protected function discover(): array
    {
        /** @var array<string, BlockSchema> $found */
        $found = [];

        foreach ([...$this->classesInBlocksDirectory(), ...$this->extra] as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, BlockSchema::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            /** @var BlockSchema $schema */
            $schema = new $class;
            $found[$class::type()] = $schema;
        }

        return $this->sorted($found);
    }

    /**
     * @return list<class-string>
     */
    protected function classesInBlocksDirectory(): array
    {
        $directory = app_path('Modules'.DIRECTORY_SEPARATOR.'CMS'.DIRECTORY_SEPARATOR.'Blocks');

        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];

        foreach (File::files($directory) as $file) {
            $name = $file->getBasename('.php');

            /** @var class-string $class */
            $class = "App\\Modules\\CMS\\Blocks\\{$name}";
            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param  array<string, BlockSchema>  $schemas
     * @return array<string, BlockSchema>
     */
    protected function sorted(array $schemas): array
    {
        /** @var list<string> $preferred */
        $preferred = array_map(strval(...), (array) config('saas.cms.block_types', []));

        $ordered = [];

        foreach ($preferred as $type) {
            if (isset($schemas[$type])) {
                $ordered[$type] = $schemas[$type];
            }
        }

        foreach ($schemas as $type => $schema) {
            $ordered[$type] ??= $schema;
        }

        return $ordered;
    }
}
