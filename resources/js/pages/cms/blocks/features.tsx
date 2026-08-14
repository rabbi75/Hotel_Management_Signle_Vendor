import { Icon } from '@/components/app-shell/icon';
import { cn } from '@/lib/utils';
import { Section, SectionHeading } from '../section';
import { COLUMN_CLASSES, rows, rowStr, str, type BlockRendererProps } from './support';

export default function FeaturesBlock({ data }: BlockRendererProps) {
    const items = rows(data, 'items');
    const columns = COLUMN_CLASSES[str(data, 'columns', '3')] ?? COLUMN_CLASSES['3'];
    const titled = str(data, 'heading') !== '' || str(data, 'subheading') !== '';

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} subheading={str(data, 'subheading')} />

            {items.length === 0 ? (
                <p className="mt-10 text-center text-sm opacity-60">No features added yet.</p>
            ) : (
                <ul className={cn('grid gap-4', columns, titled && 'mt-12')}>
                    {items.map((item, index) => (
                        <li
                            key={index}
                            className="group h-full rounded-xl border border-border bg-card p-5 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                        >
                            <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                <Icon name={rowStr(item, 'icon', 'sparkles')} className="size-5" />
                            </span>
                            <h3 className="mt-4 font-medium text-card-foreground">{rowStr(item, 'title')}</h3>
                            <p className="mt-2 text-sm text-pretty text-muted-foreground">{rowStr(item, 'description')}</p>
                        </li>
                    ))}
                </ul>
            )}
        </Section>
    );
}
