import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

export default function FaqBlock({ data }: BlockRendererProps) {
    const items = rows(data, 'items');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} />

            <div className="mx-auto max-w-3xl">
                {items.length === 0 ? (
                    <p className="mt-10 text-center text-sm opacity-60">No questions added yet.</p>
                ) : (
                    <Accordion type="single" collapsible className="mt-10 divide-y divide-border rounded-xl border border-border bg-card px-5">
                        {items.map((item, index) => (
                            <AccordionItem key={index} value={`faq-${index}`}>
                                <AccordionTrigger>{rowStr(item, 'question')}</AccordionTrigger>
                                <AccordionContent>{rowStr(item, 'answer')}</AccordionContent>
                            </AccordionItem>
                        ))}
                    </Accordion>
                )}
            </div>
        </Section>
    );
}
