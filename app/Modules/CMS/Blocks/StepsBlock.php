<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class StepsBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'steps';
    }

    public function label(): string
    {
        return __('How it works');
    }

    public function icon(): string
    {
        return 'list-checks';
    }

    public function description(): string
    {
        return __('A numbered process — typically how guests book, check in, or request a stay.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::repeater('items', __('Steps'), [
                BlockField::text('icon', __('Icon'))->rules('nullable', 'string', 'max:60'),
                BlockField::text('title', __('Title'))->rules('required', 'string', 'max:120'),
                BlockField::textarea('description', __('Description'))->rules('nullable', 'string', 'max:500'),
            ]),
        ];
    }
}
