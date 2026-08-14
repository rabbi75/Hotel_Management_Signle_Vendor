import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ArrowRight } from 'lucide-react';
import { Section } from '../section';
import { str, type BlockRendererProps } from './support';

export default function HeroBlock({ data }: BlockRendererProps) {
    const align = str(data, 'align', 'center');
    const image = str(data, 'image');
    const heading = str(data, 'heading');
    const eyebrow = str(data, 'eyebrow');
    const headingId = heading ? `hero-${heading.slice(0, 12).replace(/\W+/g, '-').toLowerCase()}` : undefined;
    const centred = align !== 'left';

    return (
        <Section
            data={data}
            aria-labelledby={headingId}
            className="pt-20 sm:pt-28"
            innerClassName={cn(centred ? 'text-center' : 'text-left')}
        >
            {image && (
                <img
                    src={image}
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 -z-10 size-full object-cover opacity-15 dark:opacity-10"
                />
            )}

            <div className={cn('max-w-3xl', centred && 'mx-auto')}>
                {eyebrow && (
                    <p className="inline-flex items-center rounded-full border border-border bg-card px-3 py-1 text-xs font-medium tracking-wide text-muted-foreground">
                        {eyebrow}
                    </p>
                )}

                <h2
                    id={headingId}
                    className={cn('text-4xl font-semibold tracking-tight text-balance sm:text-6xl', eyebrow ? 'mt-6' : 'mt-0')}
                >
                    {heading || 'Add a headline'}
                </h2>

                {str(data, 'subheading') && (
                    <p className={cn('mt-5 max-w-2xl text-lg text-balance opacity-70 sm:text-xl', centred && 'mx-auto')}>
                        {str(data, 'subheading')}
                    </p>
                )}

                {(str(data, 'primary_label') || str(data, 'secondary_label')) && (
                    <div className={cn('mt-9 flex flex-wrap items-center gap-3', centred && 'justify-center')}>
                        {str(data, 'primary_label') && (
                            <Button asChild size="lg">
                                <a href={str(data, 'primary_url', '#')}>
                                    {str(data, 'primary_label')}
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </a>
                            </Button>
                        )}
                        {str(data, 'secondary_label') && (
                            <Button asChild size="lg" variant="outline">
                                <a href={str(data, 'secondary_url', '#')}>{str(data, 'secondary_label')}</a>
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </Section>
    );
}
