<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class HeroBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return __('Hero');
    }

    public function icon(): string
    {
        return 'panel-top';
    }

    public function description(): string
    {
        return __('A headline, supporting copy and up to two calls to action.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('eyebrow', __('Eyebrow'))->rules('nullable', 'string', 'max:80'),
            BlockField::text('heading', __('Heading'))->rules('required', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::image('image', __('Background image')),
            BlockField::text('primary_label', __('Primary button'))->rules('nullable', 'string', 'max:60'),
            BlockField::url('primary_url', __('Primary link')),
            BlockField::text('secondary_label', __('Secondary button'))->rules('nullable', 'string', 'max:60'),
            BlockField::url('secondary_url', __('Secondary link')),
            BlockField::select('align', __('Alignment'))
                ->options(['left' => __('Left'), 'center' => __('Centre')])
                ->rules('nullable', 'in:left,center')
                ->default('center'),
        ];
    }
}
