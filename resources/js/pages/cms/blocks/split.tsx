import { HotelPhoto } from '@/components/public/hotel-photo';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ArrowRight } from 'lucide-react';
import { Section } from '../section';
import { str, type BlockRendererProps } from './support';

export default function SplitBlock({ data }: BlockRendererProps) {
    const imageRight = str(data, 'image_side', 'left') === 'right';

    return (
        <Section data={data}>
            <div className="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                <div className={cn(imageRight && 'lg:order-2')}>
                    <HotelPhoto
                        src={str(data, 'image')}
                        alt={str(data, 'heading')}
                        seed="about"
                        className="aspect-4/3 w-full rounded-2xl border border-border object-cover shadow-sm"
                    />
                </div>

                <div className={cn(imageRight && 'lg:order-1')}>
                    {str(data, 'eyebrow') && (
                        <p className="text-xs font-medium tracking-widest text-primary uppercase">{str(data, 'eyebrow')}</p>
                    )}
                    <h2 className={cn('text-3xl font-semibold tracking-tight text-balance sm:text-4xl', str(data, 'eyebrow') && 'mt-3')}>
                        {str(data, 'heading')}
                    </h2>
                    {str(data, 'body') && (
                        <p className="mt-4 whitespace-pre-line text-lg text-pretty text-muted-foreground">{str(data, 'body')}</p>
                    )}
                    {str(data, 'button_label') && (
                        <div className="mt-8">
                            <Button asChild>
                                <a href={str(data, 'button_url', '#')}>
                                    {str(data, 'button_label')}
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </a>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </Section>
    );
}
