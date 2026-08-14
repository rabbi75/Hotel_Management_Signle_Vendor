import { cn } from '@/lib/utils';
import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

export default function StatsBlock({ data }: BlockRendererProps) {
    const items = rows(data, 'items');
    const titled = str(data, 'heading') !== '';

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} />

            {items.length === 0 ? (
                <p className="mt-10 text-center text-sm opacity-60">No stats added yet.</p>
            ) : (
                <dl
                    className={cn(
                        'grid grid-cols-2 gap-x-6 gap-y-10',
                        items.length % 3 === 0 ? 'sm:grid-cols-3' : 'sm:grid-cols-4',
                        titled && 'mt-12',
                    )}
                >
                    {items.map((item, index) => (
                        <div key={index} className="text-center">
                            <dt className="sr-only">{rowStr(item, 'label')}</dt>
                            <dd className="bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-4xl font-semibold tracking-tight text-transparent sm:text-5xl">
                                {rowStr(item, 'value')}
                            </dd>
                            <p className="mt-2 text-sm font-medium">{rowStr(item, 'label')}</p>
                            {rowStr(item, 'description') && <p className="mt-1 text-xs opacity-60">{rowStr(item, 'description')}</p>}
                        </div>
                    ))}
                </dl>
            )}
        </Section>
    );
}
