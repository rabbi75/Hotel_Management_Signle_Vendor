import { HotelPhoto } from '@/components/public/hotel-photo';
import { Button } from '@/components/ui/button';
import { ArrowRight } from 'lucide-react';
import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

export default function OffersBlock({ data }: BlockRendererProps) {
    const items = rows(data, 'items');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} subheading={str(data, 'subheading')} />

            {items.length === 0 ? (
                <p className="mt-10 text-center text-sm opacity-60">No offers added yet.</p>
            ) : (
                <ul className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {items.map((item, index) => (
                        <li
                            key={index}
                            className="flex h-full flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md"
                        >
                            <HotelPhoto
                                src={rowStr(item, 'image')}
                                alt={rowStr(item, 'title')}
                                seed={`offer-${index}`}
                                className="aspect-4/3 w-full object-cover"
                            />
                            <div className="flex flex-1 flex-col p-5">
                                {rowStr(item, 'badge') && (
                                    <span className="mb-2 inline-flex w-fit rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                        {rowStr(item, 'badge')}
                                    </span>
                                )}
                                <h3 className="text-lg font-semibold text-card-foreground">{rowStr(item, 'title')}</h3>
                                {rowStr(item, 'description') && (
                                    <p className="mt-2 flex-1 text-sm text-pretty text-muted-foreground">{rowStr(item, 'description')}</p>
                                )}
                                {(rowStr(item, 'price') || rowStr(item, 'button_label')) && (
                                    <div className="mt-5 flex items-end justify-between gap-3">
                                        <div>
                                            {rowStr(item, 'price') && (
                                                <p className="text-xl font-semibold tracking-tight">{rowStr(item, 'price')}</p>
                                            )}
                                            {rowStr(item, 'price_note') && (
                                                <p className="text-xs text-muted-foreground">{rowStr(item, 'price_note')}</p>
                                            )}
                                        </div>
                                        {rowStr(item, 'button_label') && (
                                            <Button asChild size="sm">
                                                <a href={rowStr(item, 'button_url', '/book')}>
                                                    {rowStr(item, 'button_label')}
                                                    <ArrowRight className="size-3.5" aria-hidden="true" />
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Section>
    );
}
