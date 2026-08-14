<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class FaqBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return __('FAQ');
    }

    public function icon(): string
    {
        return 'circle-help';
    }

    public function description(): string
    {
        return __('Questions and answers in an accordion.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::repeater('items', __('Questions'), [
                BlockField::text('question', __('Question'))->rules('required', 'string', 'max:250'),
                BlockField::textarea('answer', __('Answer'))->rules('required', 'string', 'max:2000'),
            ]),
        ];
    }
}
