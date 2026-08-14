<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Enums\SecurityEvent;
use App\Modules\Audit\Services\SecurityLogger;
use App\Modules\Platform\Models\EmailTemplate;
use App\Modules\Platform\Services\EmailTemplateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function __construct(
        protected EmailTemplateResolver $resolver,
        protected SecurityLogger $security,
    ) {}

    public function index(Request $request): Response
    {
        abort_if($request->user('admin')?->cannot('platform.email_templates.manage') ?? true, 403);

        $this->resolver->seedDefaults();

        $templates = EmailTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(static fn (EmailTemplate $template): array => [
                'id' => $template->id,
                'key' => $template->key,
                'name' => $template->name,
                'subject' => $template->subject,
                'body' => $template->body,
                'placeholders' => $template->placeholders ?? [],
                'is_active' => $template->is_active,
            ])
            ->all();

        return Inertia::render('admin/email-templates/index', [
            'templates' => $templates,
        ]);
    }

    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        abort_if($request->user('admin')?->cannot('platform.email_templates.manage') ?? true, 403);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $template->forceFill($validated)->save();

        $this->security->log(
            SecurityEvent::EmailTemplateUpdated,
            $request->user('admin'),
            __('Updated email template :key.', ['key' => $template->key]),
            ['template' => $template->key],
        );

        return back()->with('success', __('Email template saved.'));
    }
}
