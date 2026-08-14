<?php

declare(strict_types=1);

namespace App\Modules\Chat;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Policies\ConversationPolicy;
use App\Modules\Chat\Policies\MessagePolicy;
use App\Modules\Chat\Services\ConversationService;
use App\Support\Modules\ModuleServiceProvider;

class ChatServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Conversation::class => ConversationPolicy::class,
        Message::class => MessagePolicy::class,
    ];

    protected function registerModule(): void
    {
        // Read several times per request — the navigation badge, the sidebar
        // list and the thread all want the same counts.
        $this->app->singleton(ConversationService::class);
    }

    protected function bootModule(): void
    {
        // Chat stays available by URL for tenants that enable it; it is not
        // surfaced in the hotel operations sidebar.
    }
}
