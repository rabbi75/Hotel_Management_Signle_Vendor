<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class SplitBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'split';
    }

    public function label(): string
    {
        return __('About / split');
    }

    public function icon(): string
    {
        return 'columns-2';
    }

    public function description(): string
    {
        return __('A professional image-and-copy band, typically used as an About the hotel section.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('eyebrow', __('Eyebrow'))->rules('nullable', 'string', 'max:80'),
            BlockField::text('heading', __('Heading'))->rules('required', 'string', 'max:160'),
            BlockField::textarea('body', __('Body'))->rules('nullable', 'string', 'max:2000'),
            BlockField::image('image', __('Image')),
            BlockField::select('image_side', __('Image side'))
                ->options(['left' => __('Left'), 'right' => __('Right')])
                ->rules('nullable', 'in:left,right')
                ->default('left'),
            BlockField::text('button_label', __('Button label'))->rules('nullable', 'string', 'max:60'),
            BlockField::url('button_url', __('Button link')),
        ];
    }
}
