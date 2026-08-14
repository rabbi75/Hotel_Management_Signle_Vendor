<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One editable field inside a block.
 *
 * The same declaration drives three things: the default value written when a
 * block is added, the validation rules applied when it is saved, and the
 * control the React editor renders. Keeping them in one place is what makes a
 * new block type a single class.
 *
 * @implements Arrayable<string, mixed>
 */
class BlockField implements Arrayable
{
    public const GROUP_CONTENT = 'content';

    public const GROUP_SECTION = 'section';

    /** @var list<string> */
    protected array $rules = [];

    /** @var list<array{value: string, label: string}> */
    protected array $options = [];

    /** @var list<self> */
    protected array $fields = [];

    protected mixed $default = null;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    /**
     * Which panel of the editor this field belongs to: the block's own content,
     * or the layout options every block shares.
     */
    protected string $group = self::GROUP_CONTENT;

    final public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type = 'text',
    ) {}

    public static function make(string $name, ?string $label = null, string $type = 'text'): static
    {
        return new static($name, $label ?? str($name)->headline()->toString(), $type);
    }

    public static function text(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'text');
    }

    public static function textarea(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'textarea');
    }

    public static function richtext(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'richtext');
    }

    public static function url(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'url')->rules('nullable', 'string', 'max:2048');
    }

    public static function image(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'image')->rules('nullable', 'string', 'max:2048');
    }

    public static function boolean(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'boolean')->rules('boolean')->default(false);
    }

    public static function number(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'number')->rules('nullable', 'numeric');
    }

    public static function select(string $name, ?string $label = null): static
    {
        return static::make($name, $label, 'select');
    }

    /**
     * A repeating group of sub-fields — features, FAQ entries, testimonials.
     *
     * @param  list<self>  $fields
     */
    public static function repeater(string $name, ?string $label, array $fields): static
    {
        $field = static::make($name, $label, 'repeater');
        $field->fields = $fields;
        $field->default = [];
        $field->rules = ['nullable', 'array'];

        return $field;
    }

    public function rules(string ...$rules): static
    {
        $this->rules = array_values($rules);

        return $this;
    }

    public function required(): static
    {
        $this->rules = ['required', ...array_values(array_filter($this->rules, static fn (string $rule): bool => $rule !== 'nullable'))];

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * @param  array<array-key, string>  $options  Value => label. Numeric keys
     *                                             are normalised to strings,
     *                                             since PHP casts "2" to 2.
     */
    public function options(array $options): static
    {
        $this->options = array_map(
            static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_map(strval(...), array_keys($options)),
            array_values($options),
        );

        return $this;
    }

    public function defaultValue(): mixed
    {
        if ($this->default !== null) {
            return $this->default;
        }

        return match ($this->type) {
            'boolean' => false,
            'repeater' => [],
            'number' => null,
            default => '',
        };
    }

    /**
     * Validation rules for this field, and for a repeater's sub-fields.
     *
     * @return array<string, list<string>>
     */
    public function validationRules(string $prefix): array
    {
        $rules = [$prefix.'.'.$this->name => $this->rules === [] ? ['nullable'] : $this->rules];

        foreach ($this->fields as $child) {
            $rules = [...$rules, ...$child->validationRules($prefix.'.'.$this->name.'.*')];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'group' => $this->group,
            'default' => $this->defaultValue(),
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'required' => in_array('required', $this->rules, true),
            'options' => $this->options,
            'fields' => array_map(static fn (self $field): array => $field->toArray(), $this->fields),
        ];
    }
}
