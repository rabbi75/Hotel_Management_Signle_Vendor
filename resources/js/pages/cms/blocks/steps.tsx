import { Icon } from '@/components/app-shell/icon';
import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

export default function StepsBlock({ data }: BlockRendererProps) {
    const items = rows(data, 'items');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} subheading={str(data, 'subheading')} />

            {items.length === 0 ? (
                <p className="mt-10 text-center text-sm opacity-60">No steps added yet.</p>
            ) : (
                <ol className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {items.map((item, index) => (
                        <li key={index} className="relative rounded-2xl border border-border bg-card p-6">
                            <span className="absolute top-4 right-4 text-4xl font-semibold text-primary/15" aria-hidden="true">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <span className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Icon name={rowStr(item, 'icon', 'check')} className="size-5" />
                            </span>
                            <h3 className="mt-4 font-medium text-card-foreground">{rowStr(item, 'title')}</h3>
                            <p className="mt-2 text-sm text-pretty text-muted-foreground">{rowStr(item, 'description')}</p>
                        </li>
                    ))}
                </ol>
            )}
        </Section>
    );
}
