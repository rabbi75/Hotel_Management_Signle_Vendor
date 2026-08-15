import { Button } from '@/components/ui/button';
import { Clock, MapPin, Navigation } from 'lucide-react';
import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

export default function LocationBlock({ data }: BlockRendererProps) {
    const map = str(data, 'map_embed_url');
    const address = str(data, 'address');
    const hours = str(data, 'hours');
    const directions = str(data, 'directions_url');
    const landmarks = rows(data, 'landmarks');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} subheading={str(data, 'subheading')} />

            <div className="mt-12 grid gap-8 lg:grid-cols-2">
                <div className="overflow-hidden rounded-2xl border border-border bg-muted/40">
                    {map ? (
                        <iframe
                            title="Hotel location map"
                            src={map}
                            className="aspect-4/3 h-full min-h-80 w-full"
                            loading="lazy"
                            referrerPolicy="no-referrer-when-downgrade"
                            allowFullScreen
                        />
                    ) : (
                        <div className="flex aspect-4/3 min-h-80 items-center justify-center p-8 text-center text-sm text-muted-foreground">
                            Add a map embed URL in the page editor to show the property on a map.
                        </div>
                    )}
                </div>

                <div className="flex flex-col justify-center">
                    {address && (
                        <p className="flex items-start gap-3 text-base">
                            <MapPin className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                            <span className="whitespace-pre-line">{address}</span>
                        </p>
                    )}
                    {hours && (
                        <p className="mt-4 flex items-start gap-3 text-sm text-muted-foreground">
                            <Clock className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
                            <span>{hours}</span>
                        </p>
                    )}
                    {directions && (
                        <div className="mt-6">
                            <Button asChild variant="outline">
                                <a href={directions} target="_blank" rel="noreferrer">
                                    <Navigation className="size-4" aria-hidden="true" />
                                    Get directions
                                </a>
                            </Button>
                        </div>
                    )}
                    {landmarks.length > 0 && (
                        <ul className="mt-8 divide-y divide-border rounded-xl border border-border bg-card">
                            {landmarks.map((item, index) => (
                                <li key={index} className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                    <span className="font-medium">{rowStr(item, 'title')}</span>
                                    <span className="text-muted-foreground">{rowStr(item, 'distance')}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </Section>
    );
}
