<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class RichtextBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'richtext';
    }

    public function label(): string
    {
        return __('Rich text');
    }

    public function icon(): string
    {
        return 'text';
    }

    public function description(): string
    {
        return __('Free-form prose.');
    }

    public function fields(): array
    {
        return [
            BlockField::richtext('content', __('Content'))->rules('nullable', 'string', 'max:50000'),
            BlockField::select('width', __('Width'))
                ->options(['prose' => __('Readable'), 'wide' => __('Full width')])
                ->rules('nullable', 'in:prose,wide')
                ->default('prose'),
        ];
    }
}
