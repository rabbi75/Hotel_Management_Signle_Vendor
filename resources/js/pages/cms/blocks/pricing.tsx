import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import { Section, SectionHeading } from '../section';
import { lines, rowBool, rows, rowStr, str, type BlockRendererProps } from './support';

export default function PricingBlock({ data }: BlockRendererProps) {
    const plans = rows(data, 'plans');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} subheading={str(data, 'subheading')} />

            <div>
                {plans.length === 0 ? (
                    <p className="mt-10 text-center text-sm opacity-60">No plans added yet.</p>
                ) : (
                    <ul className="mt-12 grid items-start gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {plans.map((plan, index) => (
                            <li key={index}>
                                <Card
                                    className={cn(
                                        'relative h-full transition-shadow hover:shadow-md',
                                        rowBool(plan, 'featured') && 'border-primary shadow-lg ring-1 ring-primary/20 lg:-mt-2 lg:pb-2',
                                    )}
                                >
                                    {rowBool(plan, 'featured') && (
                                        <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-1 text-xs font-medium text-primary-foreground shadow-sm">
                                            Most popular
                                        </span>
                                    )}
                                    <CardHeader>
                                        <CardTitle>{rowStr(plan, 'name')}</CardTitle>
                                        <CardDescription>{rowStr(plan, 'description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <p className="flex items-baseline gap-1">
                                            <span className="text-4xl font-semibold tracking-tight text-foreground">
                                                {rowStr(plan, 'price')}
                                            </span>
                                            {rowStr(plan, 'period') && (
                                                <span className="text-sm text-muted-foreground">{rowStr(plan, 'period')}</span>
                                            )}
                                        </p>

                                        <ul className="space-y-2">
                                            {lines(rowStr(plan, 'features')).map((feature) => (
                                                <li key={feature} className="flex items-start gap-2 text-sm text-muted-foreground">
                                                    <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
                                                    {feature}
                                                </li>
                                            ))}
                                        </ul>

                                        {rowStr(plan, 'cta_label') && (
                                            <Button asChild className="w-full" variant={rowBool(plan, 'featured') ? 'default' : 'outline'}>
                                                <a href={rowStr(plan, 'cta_url', '#')}>{rowStr(plan, 'cta_label')}</a>
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Section>
    );
}
