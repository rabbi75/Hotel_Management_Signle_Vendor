<?php

declare(strict_types=1);

namespace App\Modules\Audit\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * The closed set of events written to the security log. Adding a case here is
 * the only way to introduce a new security event, which keeps the log's
 * vocabulary reviewable.
 */
enum SecurityEvent: string
{
    use HasLabel;

    case PasswordChanged = 'password_changed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetCompleted = 'password_reset_completed';
    case EmailChanged = 'email_changed';
    case EmailVerified = 'email_verified';

    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';

    case SessionRevoked = 'session_revoked';
    case OtherSessionsRevoked = 'other_sessions_revoked';

    case ApiTokenCreated = 'api_token_created';
    case ApiTokenRevoked = 'api_token_revoked';

    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case PermissionsChanged = 'permissions_changed';
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case RoleDeleted = 'role_deleted';

    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case UserSuspended = 'user_suspended';
    case UserRestored = 'user_restored';
    case UserDeleted = 'user_deleted';
    case UserForceDeleted = 'user_force_deleted';
    case AccountDeleted = 'account_deleted';

    case ImpersonationStarted = 'impersonation_started';
    case ImpersonationStopped = 'impersonation_stopped';

    case WorkspaceOwnershipTransferred = 'workspace_ownership_transferred';
    case WorkspaceUpdated = 'workspace_updated';
    case WorkspaceDeleted = 'workspace_deleted';
    case WorkspaceSwitched = 'workspace_switched';
    case MemberRemoved = 'member_removed';
    case MemberRoleChanged = 'member_role_changed';
    case InvitationSent = 'invitation_sent';
    case InvitationAccepted = 'invitation_accepted';
    case InvitationRevoked = 'invitation_revoked';

    case SocialAccountLinked = 'social_account_linked';
    case SocialAccountUnlinked = 'social_account_unlinked';

    case SettingsChanged = 'settings_changed';

    case SeoSettingsChanged = 'seo_settings_changed';
    case SitemapGenerated = 'sitemap_generated';

    case SubscriptionStarted = 'subscription_started';
    case SubscriptionChanged = 'subscription_changed';
    case SubscriptionCancelled = 'subscription_cancelled';
    case SubscriptionResumed = 'subscription_resumed';
    case PaymentMethodAdded = 'payment_method_added';
    case PaymentMethodRemoved = 'payment_method_removed';
    case PlanCreated = 'plan_created';
    case PlanUpdated = 'plan_updated';
    case PlanDeleted = 'plan_deleted';
    case CouponCreated = 'coupon_created';
    case CouponUpdated = 'coupon_updated';
    case CouponDeleted = 'coupon_deleted';
    case CouponRedeemed = 'coupon_redeemed';

    case MediaUploaded = 'media_uploaded';
    case MediaUpdated = 'media_updated';
    case MediaDeleted = 'media_deleted';
    case MediaFolderDeleted = 'media_folder_deleted';

    case WebhookEndpointCreated = 'webhook_endpoint_created';
    case WebhookEndpointDeleted = 'webhook_endpoint_deleted';

    case AiProviderConfigured = 'ai_provider_configured';
    case AiCreditsAdjusted = 'ai_credits_adjusted';

    // Platform administration: an operator acting on a workspace from outside
    // it. Distinct from the subscription_* events above, which record a tenant
    // acting on their own subscription.
    case TenantPlanChanged = 'tenant_plan_changed';
    case TenantTrialExtended = 'tenant_trial_extended';
    case TenantSubscriptionCancelled = 'tenant_subscription_cancelled';
    case TenantSuspended = 'tenant_suspended';
    case TenantRestored = 'tenant_restored';
    case TenantAiCreditsAdjusted = 'tenant_ai_credits_adjusted';
    case TenantAiToggled = 'tenant_ai_toggled';
    case SupportNoteCreated = 'support_note_created';
    case AnnouncementBroadcast = 'announcement_broadcast';
    case EmailTemplateUpdated = 'email_template_updated';
    case SupportTicketCreated = 'support_ticket_created';
    case SupportTicketReplied = 'support_ticket_replied';
    case SupportTicketStatusChanged = 'support_ticket_status_changed';
    case SupportTicketAssigned = 'support_ticket_assigned';

    // Operator billing actions. Money moving is always worth a log line, and a
    // refund is the one an operator is most likely to be asked to account for.
    case PaymentGatewayConfigured = 'payment_gateway_configured';
    case PaymentRefunded = 'payment_refunded';
    case PaymentRetried = 'payment_retried';
    case GraceExtended = 'grace_extended';

    case AdminLoggedIn = 'admin_logged_in';
    case AdminLoggedOut = 'admin_logged_out';
    case AdminCreated = 'admin_created';
    case AdminUpdated = 'admin_updated';
    case AdminDeactivated = 'admin_deactivated';

    public function severity(): Severity
    {
        return match ($this) {
            self::TwoFactorDisabled,
            self::TwoFactorChallengeFailed,
            self::UserSuspended,
            self::UserForceDeleted,
            self::AccountDeleted,
            self::ImpersonationStarted,
            self::WorkspaceOwnershipTransferred,
            self::WorkspaceDeleted,
            self::PermissionsChanged,
            self::SubscriptionCancelled,
            self::TenantSuspended,
            self::TenantSubscriptionCancelled,
            self::AdminDeactivated,
            self::PaymentRefunded,
            self::MediaFolderDeleted => Severity::Warning,

            self::PasswordChanged,
            self::PasswordResetCompleted,
            self::EmailChanged,
            self::RecoveryCodesRegenerated,
            self::ApiTokenCreated,
            self::ApiTokenRevoked,
            self::RoleAssigned,
            self::RoleRevoked,
            self::RoleCreated,
            self::RoleUpdated,
            self::RoleDeleted,
            self::UserCreated,
            self::UserUpdated,
            self::UserDeleted,
            self::ImpersonationStopped,
            self::MemberRemoved,
            self::MemberRoleChanged,
            self::WorkspaceUpdated,
            self::SocialAccountLinked,
            self::SocialAccountUnlinked,
            self::SettingsChanged,
            self::SeoSettingsChanged,
            self::SubscriptionStarted,
            self::SubscriptionChanged,
            self::SubscriptionResumed,
            self::PaymentMethodAdded,
            self::PaymentMethodRemoved,
            self::PlanCreated,
            self::PlanUpdated,
            self::PlanDeleted,
            self::CouponCreated,
            self::CouponUpdated,
            self::CouponDeleted,
            self::CouponRedeemed,
            self::MediaDeleted,
            self::WebhookEndpointCreated,
            self::WebhookEndpointDeleted,
            self::AiProviderConfigured,
            self::AiCreditsAdjusted,
            self::TenantPlanChanged,
            self::TenantTrialExtended,
            self::TenantRestored,
            self::TenantAiCreditsAdjusted,
            self::TenantAiToggled,
            self::SupportNoteCreated,
            self::AnnouncementBroadcast,
            self::EmailTemplateUpdated,
            self::SupportTicketCreated,
            self::SupportTicketReplied,
            self::SupportTicketStatusChanged,
            self::SupportTicketAssigned,
            self::PaymentRetried,
            self::GraceExtended,
            self::PaymentGatewayConfigured,
            self::AdminLoggedIn,
            self::AdminLoggedOut,
            self::AdminCreated,
            self::AdminUpdated => Severity::Notice,

            default => Severity::Info,
        };
    }
}
