import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import { Quote } from 'lucide-react';
import { Section, SectionHeading } from '../section';
import { rows, rowStr, str, type BlockRendererProps } from './support';

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export default function TestimonialsBlock({ data }: BlockRendererProps) {
    const items = rows(data, 'items');

    return (
        <Section data={data}>
            <SectionHeading heading={str(data, 'heading')} />

            <div>
                {items.length === 0 ? (
                    <p className="mt-10 text-center text-sm opacity-60">No testimonials added yet.</p>
                ) : (
                    <ul className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {items.map((item, index) => {
                            const author = rowStr(item, 'author');

                            return (
                                <li key={index}>
                                    <Card className="h-full transition-shadow hover:shadow-md">
                                        <CardContent className="flex h-full flex-col gap-4 pt-6">
                                            <Quote className="size-6 shrink-0 text-primary/30" aria-hidden="true" />
                                            <blockquote className="flex-1 text-pretty text-card-foreground">
                                                “{rowStr(item, 'quote')}”
                                            </blockquote>
                                            <figcaption className="flex items-center gap-3">
                                                <Avatar size="sm">
                                                    {rowStr(item, 'avatar') && <AvatarImage src={rowStr(item, 'avatar')} alt="" />}
                                                    <AvatarFallback>{initials(author)}</AvatarFallback>
                                                </Avatar>
                                                <span className="min-w-0">
                                                    <span className="block truncate text-sm font-medium text-foreground">{author}</span>
                                                    <span className="block truncate text-xs text-muted-foreground">
                                                        {rowStr(item, 'role')}
                                                    </span>
                                                </span>
                                            </figcaption>
                                        </CardContent>
                                    </Card>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </Section>
    );
}
