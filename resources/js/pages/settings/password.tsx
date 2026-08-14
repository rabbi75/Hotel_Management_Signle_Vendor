import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SettingsLayout } from '@/layouts/settings-layout';
import { cn } from '@/lib/utils';
import type { PasswordSettingsPageProps } from '@/types/settings';
import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { FormEvent } from 'react';
import { scorePassword } from '../auth/password-strength';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';

interface PasswordForm {
    current_password: string;
    password: string;
    password_confirmation: string;
    [key: string]: string;
}

/**
 * Fortify validates this form with the `updatePassword` bag, so the request
 * declares the same bag — otherwise the errors land somewhere the page never
 * reads and a rejected change looks like nothing happened.
 */
const ERROR_BAG = 'updatePassword';

const HANDLED = ['current_password', 'password', 'password_confirmation'];

const TONE_BAR: Record<'destructive' | 'warning' | 'success', string> = {
    destructive: 'bg-destructive',
    warning: 'bg-warning',
    success: 'bg-success',
};

const TONE_TEXT: Record<'destructive' | 'warning' | 'success', string> = {
    destructive: 'text-destructive',
    warning: 'text-warning',
    success: 'text-success',
};

function formatChangedAt(value: string | null): string {
    if (value === null) {
        return 'Your password has not been changed since this account was created.';
    }

    return `Last changed ${new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' })}.`;
}

export default function PasswordSettingsPage({ passwordChangedAt }: PasswordSettingsPageProps) {
    const form = useForm<PasswordForm>({ current_password: '', password: '', password_confirmation: '' });
    const { data, setData, put, processing, errors, recentlySuccessful } = form;

    const strength = scorePassword(data.password);
    const mismatch = data.password_confirmation !== '' && data.password_confirmation !== data.password;

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        put(route('user-password.update'), {
            errorBag: ERROR_BAG,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <SettingsLayout title="Password" description="Choose a long, unique password you do not use anywhere else.">
            <ErrorSummary errors={errors} handled={HANDLED} />

            <PanelCard title="Change password" description={formatChangedAt(passwordChangedAt)}>
                <form onSubmit={submit} noValidate className="grid max-w-lg gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="current_password">Current password</Label>
                        <Input
                            id="current_password"
                            type="password"
                            name="current_password"
                            value={data.current_password}
                            autoComplete="current-password"
                            required
                            aria-invalid={Boolean(errors.current_password)}
                            aria-describedby={errors.current_password ? 'current_password-error' : undefined}
                            onChange={(event) => setData('current_password', event.target.value)}
                        />
                        {errors.current_password && (
                            <p id="current_password-error" className="text-sm font-medium text-destructive">
                                {errors.current_password}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">New password</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            autoComplete="new-password"
                            required
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={`password-strength${errors.password ? ' password-error' : ''}`}
                            onChange={(event) => setData('password', event.target.value)}
                        />

                        <div id="password-strength" aria-live="polite" className="grid gap-1.5">
                            <div className="flex gap-1" aria-hidden="true">
                                {[1, 2, 3, 4].map((step) => (
                                    <span
                                        key={step}
                                        className={cn(
                                            'h-1 flex-1 rounded-full transition-colors',
                                            data.password !== '' && strength.score >= step ? TONE_BAR[strength.tone] : 'bg-muted',
                                        )}
                                    />
                                ))}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {data.password === '' ? (
                                    'At least 12 characters, with a mix of cases, a number and a symbol.'
                                ) : (
                                    <>
                                        <span className={cn('font-medium', TONE_TEXT[strength.tone])}>{strength.label}</span>
                                        {strength.hint ? ` — ${strength.hint}` : ''}
                                    </>
                                )}
                            </p>
                        </div>

                        {errors.password && (
                            <p id="password-error" className="text-sm font-medium text-destructive">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Confirm new password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            autoComplete="new-password"
                            required
                            aria-invalid={mismatch || Boolean(errors.password_confirmation)}
                            aria-describedby={mismatch || errors.password_confirmation ? 'password_confirmation-error' : undefined}
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                        />
                        {(mismatch || errors.password_confirmation) && (
                            <p id="password_confirmation-error" className="text-sm font-medium text-destructive">
                                {errors.password_confirmation ?? 'Both passwords must match.'}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" loading={processing} disabled={processing || mismatch}>
                            Update password
                        </Button>
                        {recentlySuccessful && (
                            <p role="status" className="flex items-center gap-1.5 text-sm text-success">
                                <Check aria-hidden="true" className="size-4" />
                                Password updated
                            </p>
                        )}
                    </div>
                </form>
            </PanelCard>
        </SettingsLayout>
    );
}
