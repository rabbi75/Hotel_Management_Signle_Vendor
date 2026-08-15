import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

export default function LogosBlock({ data }: BlockRendererProps) {
    const items = rows(data, 'items');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} />

            {items.length === 0 ? (
                <p className="mt-10 text-center text-sm opacity-60">No logos added yet.</p>
            ) : (
                <ul className="mt-10 grid grid-cols-2 items-center gap-6 sm:grid-cols-3 lg:grid-cols-6">
                    {items.map((item, index) => {
                        const label = rowStr(item, 'label', 'Award');
                        const image = rowStr(item, 'image');
                        const href = rowStr(item, 'url');
                        const inner = image ? (
                            <img src={image} alt={label} className="mx-auto h-10 w-auto max-w-[8rem] object-contain opacity-70 grayscale transition hover:opacity-100 hover:grayscale-0" />
                        ) : (
                            <span className="text-center text-sm font-medium tracking-wide text-muted-foreground">{label}</span>
                        );

                        return (
                            <li key={index} className="flex min-h-16 items-center justify-center rounded-xl border border-border bg-card px-4 py-5">
                                {href ? (
                                    <a href={href} target="_blank" rel="noreferrer" className="flex w-full items-center justify-center">
                                        {inner}
                                    </a>
                                ) : (
                                    inner
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </Section>
    );
}
