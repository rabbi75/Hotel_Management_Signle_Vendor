<?php /** @var \Illuminate\Support\Collection<int, \App\Modules\Blog\Models\Post> $posts */ ?>
<?= '<?xml version="1.0" encoding="UTF-8"?>'."\n" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ route('blog.public.index') }}</link>
        <description>{{ __('The latest posts from :name.', ['name' => $title]) }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <atom:link href="{{ route('blog.public.feed') }}" rel="self" type="application/rss+xml" />

        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ $post->url() }}</link>
                <guid isPermaLink="true">{{ $post->url() }}</guid>
                <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
                @if ($post->author)
                    <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $post->author->name }}</dc:creator>
                @endif
                {{-- The excerpt only. A feed carrying the full sanitised body is
                     still the body, and syndicating it whole invites scrapers to
                     outrank the original. --}}
                <description>{{ $post->excerpt }}</description>
            </item>
        @endforeach
    </channel>
</rss>
