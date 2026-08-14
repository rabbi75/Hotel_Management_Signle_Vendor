import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Mail, MapPin, Phone } from 'lucide-react';
import { useId } from 'react';
import { Section } from '../section';
import { bool, str, type BlockRendererProps } from './support';

export default function ContactBlock({ data }: BlockRendererProps) {
    const nameId = useId();
    const emailId = useId();
    const messageId = useId();

    const email = str(data, 'email');
    const phone = str(data, 'phone');
    const address = str(data, 'address');

    return (
        <Section data={data}>
            <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
                <div>
                    {str(data, 'heading') && (
                        <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{str(data, 'heading')}</h2>
                    )}
                    {str(data, 'subheading') && <p className="mt-4 text-lg text-pretty opacity-70">{str(data, 'subheading')}</p>}

                    <ul className="mt-6 space-y-3 text-sm">
                        {email && (
                            <li className="flex items-center gap-3">
                                <Mail className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <a href={`mailto:${email}`} className="text-foreground underline-offset-4 hover:underline">
                                    {email}
                                </a>
                            </li>
                        )}
                        {phone && (
                            <li className="flex items-center gap-3">
                                <Phone className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <a href={`tel:${phone}`} className="text-foreground underline-offset-4 hover:underline">
                                    {phone}
                                </a>
                            </li>
                        )}
                        {address && (
                            <li className="flex items-start gap-3">
                                <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <span className="whitespace-pre-line text-muted-foreground">{address}</span>
                            </li>
                        )}
                    </ul>
                </div>

                {bool(data, 'show_form', true) && (
                    <form
                        className="space-y-4 rounded-xl border border-border bg-card p-6"
                        method="post"
                        action={email ? `mailto:${email}` : undefined}
                        aria-label="Contact form"
                    >
                        <div className="space-y-2">
                            <Label htmlFor={nameId}>Name</Label>
                            <Input id={nameId} name="name" autoComplete="name" required />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={emailId}>Email</Label>
                            <Input id={emailId} name="email" type="email" autoComplete="email" required />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={messageId}>Message</Label>
                            <Textarea id={messageId} name="message" rows={4} required />
                        </div>
                        <Button type="submit" className="w-full">
                            {str(data, 'submit_label', 'Send')}
                        </Button>
                    </form>
                )}
            </div>
        </Section>
    );
}
