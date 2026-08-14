<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class ContactBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'contact';
    }

    public function label(): string
    {
        return __('Contact');
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function description(): string
    {
        return __('Contact details alongside an enquiry form.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::text('email', __('Email'))->rules('nullable', 'email', 'max:191'),
            BlockField::text('phone', __('Phone'))->rules('nullable', 'string', 'max:60'),
            BlockField::textarea('address', __('Address'))->rules('nullable', 'string', 'max:500'),
            BlockField::boolean('show_form', __('Show the enquiry form'))->default(true),
            BlockField::text('submit_label', __('Submit label'))->rules('nullable', 'string', 'max:60')->default('Send'),
        ];
    }
}
