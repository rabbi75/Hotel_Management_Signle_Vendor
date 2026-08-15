import { HotelPhoto } from '@/components/public/hotel-photo';
import { Section, SectionHeading } from '../section';
import { str, type BlockRendererProps } from './support';

function embedUrl(url: string): string | null {
    if (url === '') {
        return null;
    }

    try {
        const parsed = new URL(url);

        if (parsed.hostname.includes('youtu.be')) {
            const id = parsed.pathname.replace('/', '');

            return id ? `https://www.youtube.com/embed/${id}` : null;
        }

        if (parsed.hostname.includes('youtube.com')) {
            const id = parsed.searchParams.get('v') ?? parsed.pathname.split('/').filter(Boolean).at(-1);

            return id ? `https://www.youtube.com/embed/${id}` : null;
        }

        if (parsed.hostname.includes('vimeo.com')) {
            const id = parsed.pathname.split('/').filter(Boolean).at(-1);

            return id ? `https://player.vimeo.com/video/${id}` : null;
        }
    } catch {
        return null;
    }

    return null;
}

export default function VideoBlock({ data }: BlockRendererProps) {
    const embed = embedUrl(str(data, 'video_url'));
    const poster = str(data, 'poster');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} subheading={str(data, 'subheading')} />

            <div className="mx-auto mt-12 max-w-4xl overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                {embed ? (
                    <iframe
                        title={str(data, 'heading', 'Property video')}
                        src={embed}
                        className="aspect-video w-full"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                    />
                ) : poster ? (
                    <HotelPhoto src={poster} alt={str(data, 'heading')} seed="video" className="aspect-video w-full object-cover" />
                ) : (
                    <div className="flex aspect-video items-center justify-center p-8 text-center text-sm text-muted-foreground">
                        Add a YouTube or Vimeo URL in the page editor.
                    </div>
                )}
            </div>
        </Section>
    );
}
