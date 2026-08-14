<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class GalleryBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return __('Gallery');
    }

    public function icon(): string
    {
        return 'images';
    }

    public function description(): string
    {
        return __('A grid of images with captions.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::select('columns', __('Columns'))
                ->options(['2' => '2', '3' => '3', '4' => '4'])
                ->rules('nullable', 'in:2,3,4')
                ->default('3'),
            BlockField::repeater('images', __('Images'), [
                BlockField::image('url', __('Image'))->required(),
                BlockField::text('alt', __('Alt text'))->rules('nullable', 'string', 'max:200'),
                BlockField::text('caption', __('Caption'))->rules('nullable', 'string', 'max:200'),
            ]),
        ];
    }
}
