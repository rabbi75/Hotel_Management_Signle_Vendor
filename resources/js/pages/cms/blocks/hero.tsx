import { HotelPhoto } from '@/components/public/hotel-photo';
import { HOTEL_HERO_PLACEHOLDER } from '@/lib/hotel-placeholders';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { ArrowDown, ArrowRight, CalendarDays, MapPin, Search, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { Section } from '../section';
import { str, type BlockRendererProps } from './support';

function isoDate(offsetDays: number): string {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export default function HeroBlock({ data }: BlockRendererProps) {
    const { bookingUrl } = usePage<SharedProps>().props;
    const align = str(data, 'align', 'center');
    const image = str(data, 'image', HOTEL_HERO_PLACEHOLDER);
    const heading = str(data, 'heading');
    const eyebrow = str(data, 'eyebrow');
    const headingId = heading ? `hero-${heading.slice(0, 12).replace(/\W+/g, '-').toLowerCase()}` : undefined;
    const centred = align !== 'left';
    const bookHref = str(data, 'primary_url', bookingUrl ?? '/book');
    const exploreHref = str(data, 'secondary_url', '#rooms');
    const searchAction = bookHref.split('?')[0];

    return (
        <Section
            data={data}
            aria-labelledby={headingId}
            className="overflow-visible bg-background px-0 pt-0 pb-10 text-foreground sm:pb-12"
            innerClassName="max-w-none"
        >
            <div className="relative overflow-hidden text-white">
                <HotelPhoto
                    src={image}
                    seed="hero"
                    decorative
                    className="absolute inset-0 size-full object-cover"
                />
                <div aria-hidden="true" className="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-black/25" />
                <div aria-hidden="true" className="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-black/40 to-transparent" />

                <div
                    className={cn(
                        'relative mx-auto w-full max-w-6xl px-4 pt-24 pb-28 sm:px-6 sm:pt-32 sm:pb-32',
                        centred && 'text-center',
                    )}
                >
                    {eyebrow && (
                        <p className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-medium tracking-[0.18em] text-white/90 uppercase backdrop-blur-sm">
                            <MapPin className="size-3.5" aria-hidden="true" />
                            {eyebrow}
                        </p>
                    )}

                    <h2
                        id={headingId}
                        className={cn(
                            'max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-[4.25rem] lg:leading-[1.05]',
                            eyebrow ? 'mt-6' : 'mt-0',
                            centred && 'mx-auto',
                        )}
                    >
                        {heading || 'Add a headline'}
                    </h2>

                    {str(data, 'subheading') && (
                        <p className={cn('mt-6 max-w-xl text-base text-pretty text-white/80 sm:text-lg', centred && 'mx-auto')}>
                            {str(data, 'subheading')}
                        </p>
                    )}

                    {(str(data, 'primary_label') || str(data, 'secondary_label')) && (
                        <div className={cn('mt-10 flex flex-wrap items-center gap-3', centred && 'justify-center')}>
                            {str(data, 'primary_label') && (
                                <Button asChild size="lg" className="h-12 px-6 shadow-lg">
                                    <a href={bookHref}>
                                        {str(data, 'primary_label')}
                                        <ArrowRight className="size-4" aria-hidden="true" />
                                    </a>
                                </Button>
                            )}
                            {str(data, 'secondary_label') && (
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="h-12 border-white/30 bg-white/10 px-6 text-white hover:bg-white/20 hover:text-white"
                                >
                                    <a href={exploreHref}>
                                        {str(data, 'secondary_label')}
                                        <ArrowDown className="size-4" aria-hidden="true" />
                                    </a>
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <form
                action={searchAction}
                method="get"
                className="relative z-20 mx-auto -mt-12 w-full max-w-6xl px-4 sm:-mt-16 sm:px-6"
            >
                <div className="rounded-2xl border border-border/80 bg-card p-2 text-card-foreground shadow-2xl ring-1 ring-black/5 sm:p-2.5">
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_8.5rem_auto]">
                        <Field>
                            <Label htmlFor="hero-check-in" className="flex items-center gap-1.5 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                <CalendarDays className="size-3.5" aria-hidden="true" />
                                Check-in
                            </Label>
                            <Input
                                id="hero-check-in"
                                name="check_in_date"
                                type="date"
                                defaultValue={isoDate(1)}
                                required
                                className="h-11 border-0 bg-muted/60 px-3 shadow-none focus-visible:ring-2"
                            />
                        </Field>
                        <Field>
                            <Label htmlFor="hero-check-out" className="flex items-center gap-1.5 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                <CalendarDays className="size-3.5" aria-hidden="true" />
                                Check-out
                            </Label>
                            <Input
                                id="hero-check-out"
                                name="check_out_date"
                                type="date"
                                defaultValue={isoDate(2)}
                                required
                                className="h-11 border-0 bg-muted/60 px-3 shadow-none focus-visible:ring-2"
                            />
                        </Field>
                        <Field>
                            <Label htmlFor="hero-adults" className="flex items-center gap-1.5 text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                <Users className="size-3.5" aria-hidden="true" />
                                Guests
                            </Label>
                            <Input
                                id="hero-adults"
                                name="adults"
                                type="number"
                                min={1}
                                max={20}
                                defaultValue={2}
                                className="h-11 border-0 bg-muted/60 px-3 shadow-none focus-visible:ring-2"
                            />
                        </Field>
                        <div className="flex items-end p-2 sm:col-span-2 lg:col-span-1 lg:min-w-48">
                            <Button type="submit" size="lg" className="h-11 w-full">
                                <Search className="size-4" aria-hidden="true" />
                                Check availability
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
        </Section>
    );
}

function Field({ children }: { children: ReactNode }) {
    return <div className="flex flex-col gap-2 rounded-xl px-3 py-2.5">{children}</div>;
}
