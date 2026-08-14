import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ArrowRight } from 'lucide-react';
import { Section, SectionGlow } from '../section';
import { str, type BlockRendererProps } from './support';

export default function CtaBlock({ data }: BlockRendererProps) {
    const primary = str(data, 'tone', 'primary') === 'primary';

    return (
        <Section data={data}>
            <div
                className={cn(
                    'relative isolate overflow-hidden rounded-2xl border px-6 py-14 text-center sm:px-12',
                    primary ? 'border-primary/20 bg-card shadow-sm' : 'border-border bg-muted/50',
                )}
            >
                {primary && <SectionGlow />}

                <h2 className="mx-auto max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    {str(data, 'heading')}
                </h2>

                {str(data, 'body') && <p className="mx-auto mt-4 max-w-xl text-pretty text-muted-foreground">{str(data, 'body')}</p>}

                {str(data, 'button_label') && (
                    <div className="mt-8 flex justify-center">
                        <Button asChild size="lg" variant={primary ? 'default' : 'outline'}>
                            <a href={str(data, 'button_url', '#')}>
                                {str(data, 'button_label')}
                                <ArrowRight className="size-4" aria-hidden="true" />
                            </a>
                        </Button>
                    </div>
                )}
            </div>
        </Section>
    );
}
