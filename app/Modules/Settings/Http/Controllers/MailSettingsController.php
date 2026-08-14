<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Modules\Settings\DTOs\MailSettingsData;
use App\Modules\Settings\Http\Requests\SendTestMailRequest;
use App\Modules\Settings\Http\Requests\UpdateMailSettingsRequest;
use App\Modules\Settings\Mail\TestMail;
use App\Modules\Settings\Support\SettingsSchema;
use App\Support\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Response;
use Throwable;

class MailSettingsController extends SettingsController
{
    /**
     * Name of the throwaway mailer the test message is sent through, so a bad
     * configuration can never poison the application's real mailer.
     */
    protected const TEST_MAILER = 'settings_test';

    public function index(): Response
    {
        return $this->panel('admin/settings/mail', SettingsSchema::GROUP_MAIL, [
            'transports' => ['smtp', 'ses', 'postmark', 'resend', 'sendmail', 'log', 'array'],
        ]);
    }

    public function update(UpdateMailSettingsRequest $request): RedirectResponse
    {
        return $this->persist(MailSettingsData::fromRequest($request), $request);
    }

    /**
     * Send a message to the current user through the *saved* mail settings.
     *
     * A failure is surfaced with the transport's own message rather than a
     * friendly summary: "Connection could not be established with host
     * smtp.example.com" is the only thing that actually helps an administrator.
     */
    public function sendTest(SendTestMailRequest $request): RedirectResponse
    {
        $user = $request->user();
        $recipient = $request->string('recipient')->toString() ?: (string) ($user?->getAttribute('email') ?? '');

        if ($recipient === '') {
            return back()->withErrors(['recipient' => __('No recipient address available.')]);
        }

        try {
            $this->configureTestMailer();

            Mail::mailer(self::TEST_MAILER)
                ->to($recipient)
                ->send(new TestMail(
                    (string) $this->settings->get('general.app_name', config('saas.brand.name')),
                    (string) ($user?->getAttribute('name') ?? __('an administrator')),
                ));
        } catch (Throwable $exception) {
            return back()
                ->withErrors(['mail' => $exception->getMessage()])
                ->with('error', __('Test email failed: :message', ['message' => $exception->getMessage()]));
        }

        return back()->with('success', __('Test email sent to :address.', ['address' => $recipient]));
    }

    /**
     * Materialise the persisted settings into a runtime mailer definition.
     */
    protected function configureTestMailer(): void
    {
        $values = SettingsSchema::values(SettingsSchema::GROUP_MAIL, $this->settings, SettingsRepository::SCOPE_SYSTEM);
        $transport = is_string($values['mailer'] ?? null) && $values['mailer'] !== '' ? $values['mailer'] : 'smtp';
        $encryption = $values['encryption'] ?? null;

        config([
            'mail.mailers.'.self::TEST_MAILER => [
                'transport' => $transport,
                'host' => $values['host'] ?: null,
                'port' => (int) ($values['port'] ?: 587),
                'username' => $values['username'] ?: null,
                'password' => $values['password'] ?: null,
                'encryption' => $encryption === 'none' ? null : $encryption,
                'timeout' => 10,
            ],
            'mail.from.address' => $values['from_address'] ?: config('mail.from.address'),
            'mail.from.name' => $values['from_name'] ?: config('mail.from.name'),
        ]);

        // Drop any mailer instance built from a previous attempt's config.
        Mail::purge(self::TEST_MAILER);
    }
}
