import { useConfirm } from '@/components/feedback/use-confirm';
import { FormActions } from '@/components/forms/form-actions';
import { ImageUpload } from '@/components/forms/image-upload';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { SettingsLayout } from '@/layouts/settings-layout';
import { ErrorSummary, PanelCard } from '@/components/forms/settings-panel';
import type { Appearance, SharedProps } from '@/types';
import type { ProfilePageProps } from '@/types/settings';
import { router, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';

/*
| The controller sends no timezone list, so the browser's own IANA database is
| used. `Intl.supportedValuesOf` is unavailable in a few older engines, hence the
| fallback to whatever the user already has saved.
*/
function timezoneOptions(current: string): string[] {
    try {
        const zones = Intl.supportedValuesOf('timeZone');

        return zones.includes(current) ? zones : [current, ...zones];
    } catch {
        return [current];
    }
}

/** Topics the kit ships with; anything else already stored is rendered too. */
const NOTIFICATION_LABELS: Record<string, string> = {
    security_alerts: 'Security alerts',
    product_updates: 'Product updates',
    workspace_activity: 'Workspace activity',
    mentions: 'Mentions and replies',
    weekly_digest: 'Weekly digest',
};

function humanise(key: string): string {
    const label = key.replace(/[_-]+/g, ' ').trim();

    return label.charAt(0).toUpperCase() + label.slice(1);
}

interface ProfileForm {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    job_title: string;
    bio: string;
    [key: string]: string;
}

interface PreferencesForm {
    timezone: string;
    locale: string;
    theme: Appearance;
    notifications: Record<string, boolean>;
    [key: string]: string | Record<string, boolean>;
}

export default function ProfileShow({ user, preferences, locales, themes }: ProfilePageProps) {
    const { errors } = usePage<SharedProps>().props;
    const confirm = useConfirm();

    const zones = useMemo(() => timezoneOptions(preferences.timezone), [preferences.timezone]);

    const notificationKeys = useMemo(() => {
        const stored = Object.entries(preferences.notifications)
            .filter(([, value]) => typeof value === 'boolean')
            .map(([key]) => key);

        return Array.from(new Set([...Object.keys(NOTIFICATION_LABELS), ...stored]));
    }, [preferences.notifications]);

    const profile = useForm<ProfileForm>({
        first_name: user.first_name ?? '',
        last_name: user.last_name ?? '',
        email: user.email,
        phone: user.phone ?? '',
        job_title: user.job_title ?? '',
        bio: user.bio ?? '',
    });

    const prefs = useForm<PreferencesForm>({
        timezone: preferences.timezone,
        locale: preferences.locale,
        theme: preferences.theme,
        notifications: Object.fromEntries(notificationKeys.map((key) => [key, preferences.notifications[key] === true])),
    });

    const [avatar, setAvatar] = useState<File | null>(null);
    const [avatarBusy, setAvatarBusy] = useState(false);
    const [deletePassword, setDeletePassword] = useState('');
    const [deleting, setDeleting] = useState(false);

    function saveProfile(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        profile.put(route('profile.update'), { preserveScroll: true });
    }

    function savePreferences(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        prefs.put(route('profile.preferences.update'), { preserveScroll: true });
    }

    function uploadAvatar(): void {
        if (!avatar) {
            return;
        }

        setAvatarBusy(true);
        router.post(
            route('profile.avatar.store'),
            { avatar },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setAvatar(null),
                onFinish: () => setAvatarBusy(false),
            },
        );
    }

    async function removeAvatar(): Promise<void> {
        const ok = await confirm({
            title: 'Remove your profile photo?',
            description: 'Your initials will be shown instead.',
            confirmLabel: 'Remove photo',
            variant: 'destructive',
        });

        if (!ok) {
            return;
        }

        setAvatarBusy(true);
        router.delete(route('profile.avatar.destroy'), {
            preserveScroll: true,
            onSuccess: () => setAvatar(null),
            onFinish: () => setAvatarBusy(false),
        });
    }

    async function deleteAccount(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();

        const ok = await confirm({
            title: 'Delete your account?',
            description: 'This removes your account and everything only you can see. It cannot be undone.',
            confirmLabel: 'Delete my account',
            variant: 'destructive',
            confirmWord: 'delete',
        });

        if (!ok) {
            return;
        }

        setDeleting(true);
        router.delete(route('profile.destroy'), {
            data: { password: deletePassword },
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setDeletePassword('');
            },
        });
    }

    const handled = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'bio',
        'timezone',
        'locale',
        'theme',
        'notifications',
        'avatar',
        'password',
    ];

    return (
        <SettingsLayout title="Profile" description="Your name, contact details and how the application behaves for you.">
            <ErrorSummary errors={errors} handled={handled} />

            <PanelCard title="Profile photo" description="A square image works best. PNG or JPG, up to 4 MB.">
                <div className="space-y-4">
                    <ImageUpload
                        value={avatar ?? user.avatar_url}
                        onChange={(file) => setAvatar(file)}
                        shape="circle"
                        label="Profile photo"
                        maxSizeMb={4}
                        disabled={avatarBusy}
                        invalid={Boolean(errors.avatar)}
                        describedBy={errors.avatar ? 'avatar-error' : undefined}
                    />

                    {errors.avatar && (
                        <p id="avatar-error" className="text-sm font-medium text-destructive">
                            {errors.avatar}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={uploadAvatar} loading={avatarBusy} disabled={!avatar || avatarBusy}>
                            Save photo
                        </Button>
                        {user.avatar_url && (
                            <Button type="button" variant="ghost" onClick={() => void removeAvatar()} disabled={avatarBusy}>
                                Remove current photo
                            </Button>
                        )}
                    </div>
                </div>
            </PanelCard>

            <PanelCard
                title="Personal details"
                description="Shown to other members of your workspaces."
                action={
                    <>
                        <Badge variant={user.email_verified ? 'success' : 'warning'}>
                            {user.email_verified ? 'Email verified' : 'Email unverified'}
                        </Badge>
                        {user.two_factor_enabled && <Badge variant="secondary">2FA on</Badge>}
                    </>
                }
            >
                <form onSubmit={saveProfile} noValidate className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First name</Label>
                            <Input
                                id="first_name"
                                value={profile.data.first_name}
                                autoComplete="given-name"
                                required
                                aria-invalid={Boolean(errors.first_name)}
                                aria-describedby={errors.first_name ? 'first_name-error' : undefined}
                                onChange={(event) => profile.setData('first_name', event.target.value)}
                            />
                            {errors.first_name && (
                                <p id="first_name-error" className="text-sm font-medium text-destructive">
                                    {errors.first_name}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                value={profile.data.last_name}
                                autoComplete="family-name"
                                required
                                aria-invalid={Boolean(errors.last_name)}
                                aria-describedby={errors.last_name ? 'last_name-error' : undefined}
                                onChange={(event) => profile.setData('last_name', event.target.value)}
                            />
                            {errors.last_name && (
                                <p id="last_name-error" className="text-sm font-medium text-destructive">
                                    {errors.last_name}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                value={profile.data.email}
                                autoComplete="email"
                                required
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                                onChange={(event) => profile.setData('email', event.target.value)}
                            />
                            {errors.email && (
                                <p id="email-error" className="text-sm font-medium text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                type="tel"
                                value={profile.data.phone}
                                autoComplete="tel"
                                aria-invalid={Boolean(errors.phone)}
                                aria-describedby={errors.phone ? 'phone-error' : undefined}
                                onChange={(event) => profile.setData('phone', event.target.value)}
                            />
                            {errors.phone && (
                                <p id="phone-error" className="text-sm font-medium text-destructive">
                                    {errors.phone}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="job_title">Job title</Label>
                            <Input
                                id="job_title"
                                value={profile.data.job_title}
                                autoComplete="organization-title"
                                aria-invalid={Boolean(errors.job_title)}
                                aria-describedby={errors.job_title ? 'job_title-error' : undefined}
                                onChange={(event) => profile.setData('job_title', event.target.value)}
                            />
                            {errors.job_title && (
                                <p id="job_title-error" className="text-sm font-medium text-destructive">
                                    {errors.job_title}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="bio">Bio</Label>
                            <Textarea
                                id="bio"
                                rows={4}
                                value={profile.data.bio}
                                maxLength={1000}
                                aria-invalid={Boolean(errors.bio)}
                                aria-describedby={`bio-description${errors.bio ? ' bio-error' : ''}`}
                                onChange={(event) => profile.setData('bio', event.target.value)}
                            />
                            <p id="bio-description" className="text-xs text-muted-foreground">
                                {profile.data.bio.length} of 1000 characters.
                            </p>
                            {errors.bio && (
                                <p id="bio-error" className="text-sm font-medium text-destructive">
                                    {errors.bio}
                                </p>
                            )}
                        </div>
                    </div>

                    <FormActions
                        dirty={profile.isDirty}
                        submitting={profile.processing}
                        saved={profile.recentlySuccessful}
                        sticky={false}
                        onCancel={() => profile.reset()}
                    />
                </form>
            </PanelCard>

            <PanelCard title="Preferences" description="How dates, language and colours are presented to you.">
                <form onSubmit={savePreferences} noValidate className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="timezone">Timezone</Label>
                            <Select value={prefs.data.timezone} onValueChange={(value) => prefs.setData('timezone', value)}>
                                <SelectTrigger
                                    id="timezone"
                                    aria-invalid={Boolean(errors.timezone)}
                                    aria-describedby={errors.timezone ? 'timezone-error' : undefined}
                                >
                                    <SelectValue placeholder="Select a timezone" />
                                </SelectTrigger>
                                <SelectContent>
                                    {zones.map((zone) => (
                                        <SelectItem key={zone} value={zone}>
                                            {zone.replace(/_/g, ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.timezone && (
                                <p id="timezone-error" className="text-sm font-medium text-destructive">
                                    {errors.timezone}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="locale">Language</Label>
                            <Select value={prefs.data.locale} onValueChange={(value) => prefs.setData('locale', value)}>
                                <SelectTrigger
                                    id="locale"
                                    aria-invalid={Boolean(errors.locale)}
                                    aria-describedby={errors.locale ? 'locale-error' : undefined}
                                >
                                    <SelectValue placeholder="Select a language" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(locales).map(([code, definition]) => (
                                        <SelectItem key={code} value={code}>
                                            {definition.native}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.locale && (
                                <p id="locale-error" className="text-sm font-medium text-destructive">
                                    {errors.locale}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="theme">Colour scheme</Label>
                            <Select value={prefs.data.theme} onValueChange={(value) => prefs.setData('theme', value as Appearance)}>
                                <SelectTrigger
                                    id="theme"
                                    aria-invalid={Boolean(errors.theme)}
                                    aria-describedby={errors.theme ? 'theme-error' : undefined}
                                >
                                    <SelectValue placeholder="Select a colour scheme" />
                                </SelectTrigger>
                                <SelectContent>
                                    {themes.map((theme) => (
                                        <SelectItem key={theme.value} value={theme.value}>
                                            {theme.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.theme && (
                                <p id="theme-error" className="text-sm font-medium text-destructive">
                                    {errors.theme}
                                </p>
                            )}
                        </div>
                    </div>

                    <fieldset className="space-y-3 border-t border-border pt-5">
                        <legend className="sr-only">Notification preferences</legend>
                        <h3 className="text-sm font-medium">Notifications</h3>

                        <ul className="divide-y divide-border">
                            {notificationKeys.map((key) => (
                                <li key={key} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                    <Label htmlFor={`notify-${key}`} className="text-sm font-normal">
                                        {NOTIFICATION_LABELS[key] ?? humanise(key)}
                                    </Label>
                                    <Switch
                                        id={`notify-${key}`}
                                        checked={prefs.data.notifications[key] === true}
                                        onCheckedChange={(checked) =>
                                            prefs.setData('notifications', { ...prefs.data.notifications, [key]: checked === true })
                                        }
                                    />
                                </li>
                            ))}
                        </ul>

                        {errors.notifications && <p className="text-sm font-medium text-destructive">{errors.notifications}</p>}
                    </fieldset>

                    <FormActions
                        dirty={prefs.isDirty}
                        submitting={prefs.processing}
                        saved={prefs.recentlySuccessful}
                        sticky={false}
                        submitLabel="Save preferences"
                        onCancel={() => prefs.reset()}
                    />
                </form>
            </PanelCard>

            <PanelCard title="Delete account" description="Permanently remove your account and the data only you can see.">
                <Alert variant="destructive" className="mb-4">
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>This cannot be undone</AlertTitle>
                    <AlertDescription>
                        Transfer or delete any workspace you own first — the server refuses to delete an account that others still depend
                        on.
                    </AlertDescription>
                </Alert>

                <form onSubmit={(event) => void deleteAccount(event)} noValidate className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="delete-password">Confirm with your password</Label>
                        <Input
                            id="delete-password"
                            type="password"
                            value={deletePassword}
                            autoComplete="current-password"
                            required
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={errors.password ? 'delete-password-error' : undefined}
                            onChange={(event) => setDeletePassword(event.target.value)}
                        />
                        {errors.password && (
                            <p id="delete-password-error" className="text-sm font-medium text-destructive">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <Button type="submit" variant="destructive" loading={deleting} disabled={deleting || deletePassword === ''}>
                        Delete my account
                    </Button>
                </form>
            </PanelCard>
        </SettingsLayout>
    );
}
