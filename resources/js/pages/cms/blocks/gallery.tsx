import { cn } from '@/lib/utils';
import { Section, SectionHeading } from '../section';
import { COLUMN_CLASSES, rows, rowStr, str, type BlockRendererProps } from './support';

export default function GalleryBlock({ data }: BlockRendererProps) {
    const images = rows(data, 'images').filter((image) => rowStr(image, 'url') !== '');
    const columns = COLUMN_CLASSES[str(data, 'columns', '3')] ?? COLUMN_CLASSES['3'];

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} />

            <div>
                {images.length === 0 ? (
                    <p className="mt-10 text-center text-sm opacity-60">No images added yet.</p>
                ) : (
                    <ul className={cn('mt-12 grid gap-4', columns)}>
                        {images.map((image, index) => (
                            <li key={index}>
                                <figure className="space-y-2">
                                    <img
                                        src={rowStr(image, 'url')}
                                        alt={rowStr(image, 'alt')}
                                        loading="lazy"
                                        className="aspect-4/3 w-full rounded-xl border border-border object-cover transition-transform duration-300 hover:scale-[1.02]"
                                    />
                                    {rowStr(image, 'caption') && (
                                        <figcaption className="text-xs text-muted-foreground">{rowStr(image, 'caption')}</figcaption>
                                    )}
                                </figure>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Section>
    );
}
