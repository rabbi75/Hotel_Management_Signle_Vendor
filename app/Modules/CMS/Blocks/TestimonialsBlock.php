<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class TestimonialsBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'testimonials';
    }

    public function label(): string
    {
        return __('Testimonials');
    }

    public function icon(): string
    {
        return 'quote';
    }

    public function description(): string
    {
        return __('Customer quotes with an attribution and optional portrait.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::repeater('items', __('Testimonials'), [
                BlockField::textarea('quote', __('Quote'))->rules('required', 'string', 'max:1000'),
                BlockField::text('author', __('Author'))->rules('required', 'string', 'max:120'),
                BlockField::text('role', __('Role'))->rules('nullable', 'string', 'max:120'),
                BlockField::image('avatar', __('Portrait')),
            ]),
        ];
    }
}
