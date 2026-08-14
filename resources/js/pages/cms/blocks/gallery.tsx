import { HotelPhoto } from '@/components/public/hotel-photo';
import { HOTEL_PLACEHOLDER_IMAGES } from '@/lib/hotel-placeholders';
import { cn } from '@/lib/utils';
import type { BlockRepeaterRow } from '@/types/cms';
import { Section, SectionHeading } from '../section';
import { COLUMN_CLASSES, rows, rowStr, str, type BlockRendererProps } from './support';

export default function GalleryBlock({ data }: BlockRendererProps) {
    const authored = rows(data, 'images');
    const images =
        authored.length > 0
            ? authored
            : HOTEL_PLACEHOLDER_IMAGES.slice(0, 6).map((url): BlockRepeaterRow => ({
                  url,
                  alt: 'Hotel',
                  caption: '',
              }));
    const columns = COLUMN_CLASSES[str(data, 'columns', '3')] ?? COLUMN_CLASSES['3'];

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} />

            <ul className={cn('mt-12 grid gap-4', columns)}>
                {images.map((image, index) => (
                    <li key={index}>
                        <figure className="space-y-2">
                            <HotelPhoto
                                src={rowStr(image, 'url')}
                                alt={rowStr(image, 'alt')}
                                seed={index}
                                className="aspect-4/3 w-full rounded-xl border border-border transition-transform duration-300 hover:scale-[1.02]"
                            />
                            {rowStr(image, 'caption') && (
                                <figcaption className="text-xs text-muted-foreground">{rowStr(image, 'caption')}</figcaption>
                            )}
                        </figure>
                    </li>
                ))}
            </ul>
        </Section>
    );
}
