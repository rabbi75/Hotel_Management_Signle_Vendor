<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class CtaBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return __('Call to action');
    }

    public function icon(): string
    {
        return 'megaphone';
    }

    public function description(): string
    {
        return __('A single banner that asks the reader to do one thing.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('required', 'string', 'max:160'),
            BlockField::textarea('body', __('Body'))->rules('nullable', 'string', 'max:500'),
            BlockField::text('button_label', __('Button label'))->rules('nullable', 'string', 'max:60'),
            BlockField::url('button_url', __('Button link')),
            BlockField::select('tone', __('Tone'))
                ->options(['muted' => __('Muted'), 'primary' => __('Primary')])
                ->rules('nullable', 'in:muted,primary')
                ->default('primary'),
        ];
    }
}
