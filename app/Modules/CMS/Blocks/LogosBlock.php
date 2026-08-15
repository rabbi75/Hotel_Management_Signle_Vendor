<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class LogosBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'logos';
    }

    public function label(): string
    {
        return __('Awards / logos');
    }

    public function icon(): string
    {
        return 'star';
    }

    public function description(): string
    {
        return __('A strip of awards, press mentions or partner logos.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::repeater('items', __('Logos'), [
                BlockField::image('image', __('Logo')),
                BlockField::text('label', __('Label'))->rules('nullable', 'string', 'max:120'),
                BlockField::url('url', __('Link')),
            ]),
        ];
    }
}
