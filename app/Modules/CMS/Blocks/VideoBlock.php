<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class VideoBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'video';
    }

    public function label(): string
    {
        return __('Video');
    }

    public function icon(): string
    {
        return 'monitor';
    }

    public function description(): string
    {
        return __('An embedded property film or highlight reel from YouTube or Vimeo.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::url('video_url', __('Video URL'))
                ->help(__('A YouTube or Vimeo watch/share link.')),
            BlockField::image('poster', __('Poster image')),
        ];
    }
}
