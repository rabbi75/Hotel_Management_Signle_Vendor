<?php

declare(strict_types=1);

use App\Http\Middleware\SetCurrentCompany;
use App\Modules\Billing\Http\Controllers\CouponController;
use App\Modules\Billing\Http\Controllers\PlanController;
use App\Modules\Platform\Http\Controllers\AdminController;
use App\Modules\Platform\Http\Controllers\AdminProfileController;
use App\Modules\Platform\Http\Controllers\AdminTwoFactorController;
use App\Modules\Platform\Http\Controllers\Auth\AdminLoginController;
use App\Modules\Platform\Http\Controllers\Auth\AdminPasswordConfirmController;
use App\Modules\Platform\Http\Controllers\Auth\AdminPasswordResetController;
use App\Modules\Platform\Http\Controllers\Auth\AdminTwoFactorChallengeController;
use App\Modules\Platform\Http\Controllers\Billing\PlatformDunningController;
use App\Modules\Platform\Http\Controllers\Billing\PlatformInvoiceController;
use App\Modules\Platform\Http\Controllers\Billing\PlatformRevenueController;
use App\Modules\Platform\Http\Controllers\Billing\PlatformWebhookEventController;
use App\Modules\Platform\Http\Controllers\CMS\PlatformPageBlockController;
use App\Modules\Platform\Http\Controllers\CMS\PlatformPageController;
use App\Modules\Platform\Http\Controllers\CMS\PlatformPagePreviewController;
use App\Modules\Platform\Http\Controllers\CMS\PlatformPagePublishController;
use App\Modules\Platform\Http\Controllers\Content\PlatformCategoryController;
use App\Modules\Platform\Http\Controllers\Content\PlatformCommentController;
use App\Modules\Platform\Http\Controllers\Content\PlatformMediaController;
use App\Modules\Platform\Http\Controllers\Content\PlatformMediaFolderController;
use App\Modules\Platform\Http\Controllers\Content\PlatformMediaUploadController;
use App\Modules\Platform\Http\Controllers\Content\PlatformMenuController;
use App\Modules\Platform\Http\Controllers\Content\PlatformMenuItemController;
use App\Modules\Platform\Http\Controllers\Content\PlatformPostController;
use App\Modules\Platform\Http\Controllers\Content\PlatformPostPublishController;
use App\Modules\Platform\Http\Controllers\Content\PlatformSeoController;
use App\Modules\Platform\Http\Controllers\Content\PlatformTagController;
use App\Modules\Platform\Http\Controllers\EmailTemplateController;
use App\Modules\Platform\Http\Controllers\PlatformAiUsageController;
use App\Modules\Platform\Http\Controllers\PlatformAnnouncementController;
use App\Modules\Platform\Http\Controllers\PlatformAppearanceController;
use App\Modules\Platform\Http\Controllers\PlatformAuditController;
use App\Modules\Platform\Http\Controllers\PlatformDashboardController;
use App\Modules\Platform\Http\Controllers\PlatformGatewayController;
use App\Modules\Platform\Http\Controllers\PlatformTicketController;
use App\Modules\Platform\Http\Controllers\PlatformUserController;
use App\Modules\Platform\Http\Controllers\TenantController;
use App\Modules\Platform\Http\Controllers\TenantImpersonationController;
use App\Modules\Platform\Http\Controllers\TenantOpsController;
use App\Modules\Platform\Http\Controllers\TenantSubscriptionController;
use App\Modules\Platform\Http\Middleware\EnsureActiveAdmin;
use Illuminate\Support\Facades\Route;

if (single_vendor()) {
    return;
}

/*
|------------------------------------------------------------------------------
| Platform (operator console) routes
|------------------------------------------------------------------------------
|
| A world apart from the tenant app: its own `admin` guard, its own login, and
| — throughout — SetCurrentCompany is stripped so CompanyScope is a no-op and
| every query spans all tenants. Anything that must act as a tenant wraps itself
| in CurrentCompany::scopeTo().
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->withoutMiddleware(SetCurrentCompany::class)
    ->group(function (): void {

        // -- Guest auth (own guard) ---------------------------------------------
        Route::middleware('guest:admin')->group(function (): void {
            Route::get('login', [AdminLoginController::class, 'create'])->name('login');
            Route::post('login', [AdminLoginController::class, 'store'])
                ->middleware('throttle:login');

            Route::get('forgot-password', [AdminPasswordResetController::class, 'requestView'])->name('password.request');
            Route::post('forgot-password', [AdminPasswordResetController::class, 'sendLink'])->name('password.email');
            Route::get('reset-password/{token}', [AdminPasswordResetController::class, 'resetView'])->name('password.reset');
            Route::post('reset-password', [AdminPasswordResetController::class, 'reset'])->name('password.update');

            Route::get('two-factor-challenge', [AdminTwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
            Route::post('two-factor-challenge', [AdminTwoFactorChallengeController::class, 'store'])
                ->middleware('throttle:two-factor');
        });

        Route::post('logout', [AdminLoginController::class, 'destroy'])
            ->middleware('auth:admin')
            ->name('logout');

        // The password re-challenge. Signed in already, so `auth:admin` rather
        // than `guest`, and deliberately outside the group below so an operator
        // being asked to confirm is never bounced through the same check again.
        Route::middleware('auth:admin')->group(function (): void {
            Route::get('confirm-password', [AdminPasswordConfirmController::class, 'show'])->name('password.confirm');
            Route::post('confirm-password', [AdminPasswordConfirmController::class, 'store'])
                ->middleware('throttle:login')
                ->name('password.confirm.store');
        });

        // -- The console --------------------------------------------------------
        Route::middleware(['auth:admin', EnsureActiveAdmin::class])->group(function (): void {

            Route::get('/', [PlatformDashboardController::class, 'index'])->name('dashboard');

            // -- Tenants --------------------------------------------------------
            Route::prefix('tenants')->name('tenants.')->group(function (): void {
                Route::get('/', [TenantController::class, 'index'])->name('index');
                Route::get('{company}', [TenantController::class, 'show'])->name('show');
                Route::patch('{company}/status', [TenantController::class, 'updateStatus'])->name('status');

                Route::post('{company}/impersonate', [TenantImpersonationController::class, 'store'])
                    ->name('impersonate');

                Route::prefix('{company}/subscription')->name('subscription.')->group(function (): void {
                    Route::put('/', [TenantSubscriptionController::class, 'update'])->name('update');
                    Route::post('trial', [TenantSubscriptionController::class, 'extendTrial'])->name('trial');
                    Route::delete('/', [TenantSubscriptionController::class, 'destroy'])->name('cancel');
                });

                Route::post('{company}/ai/credits', [TenantOpsController::class, 'adjustAiCredits'])->name('ai.credits');
                Route::patch('{company}/ai', [TenantOpsController::class, 'toggleAi'])->name('ai.toggle');
                Route::post('{company}/notes', [TenantOpsController::class, 'storeNote'])->name('notes.store');
                Route::delete('{company}/notes/{note}', [TenantOpsController::class, 'destroyNote'])->name('notes.destroy');
            });

            // -- Users ----------------------------------------------------------
            Route::prefix('users')->name('users.')->group(function (): void {
                Route::get('/', [PlatformUserController::class, 'index'])->name('index');
                Route::patch('{user}/suspend', [PlatformUserController::class, 'suspend'])->name('suspend');
                Route::patch('{user}/restore', [PlatformUserController::class, 'restore'])->name('restore');
            });

            Route::get('ai/usage', [PlatformAiUsageController::class, 'index'])->name('ai.usage');

            Route::prefix('tickets')->name('tickets.')->group(function (): void {
                Route::get('/', [PlatformTicketController::class, 'index'])->name('index');
                Route::get('{ticket}', [PlatformTicketController::class, 'show'])->name('show');
                Route::post('{ticket}/messages', [PlatformTicketController::class, 'reply'])->name('reply');
                Route::patch('{ticket}', [PlatformTicketController::class, 'update'])->name('update');
            });

            Route::get('audit', [PlatformAuditController::class, 'index'])->name('audit.index');

            Route::prefix('announcements')->name('announcements.')->group(function (): void {
                Route::get('/', [PlatformAnnouncementController::class, 'index'])->name('index');
                Route::post('/', [PlatformAnnouncementController::class, 'store'])->name('store');
            });

            Route::prefix('email-templates')->name('email-templates.')->group(function (): void {
                Route::get('/', [EmailTemplateController::class, 'index'])->name('index');
                Route::put('{template}', [EmailTemplateController::class, 'update'])->name('update');
            });

            // -- Admins (super-admin only, gated in the controller) -------------
            Route::prefix('admins')->name('admins.')->group(function (): void {
                Route::get('/', [AdminController::class, 'index'])->name('index');
                Route::post('/', [AdminController::class, 'store'])->name('store');
                Route::match(['put', 'patch'], '{admin}', [AdminController::class, 'update'])->name('update');
                Route::patch('{admin}/deactivate', [AdminController::class, 'deactivate'])->name('deactivate');
                Route::patch('{admin}/reactivate', [AdminController::class, 'reactivate'])->name('reactivate');
            });

            // -- The signed-in admin's own account ------------------------------
            Route::prefix('account')->name('account.')->group(function (): void {
                Route::get('/', [AdminProfileController::class, 'edit'])->name('edit');
                Route::put('/', [AdminProfileController::class, 'update'])->name('update');
                Route::put('password', [AdminProfileController::class, 'updatePassword'])->name('password');

                Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
                    Route::post('/', [AdminTwoFactorController::class, 'store'])->name('enable');
                    Route::post('confirm', [AdminTwoFactorController::class, 'confirm'])->name('confirm');
                    Route::get('recovery-codes', [AdminTwoFactorController::class, 'recoveryCodes'])->name('recovery-codes');
                    Route::post('recovery-codes', [AdminTwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
                    Route::delete('/', [AdminTwoFactorController::class, 'destroy'])->name('disable');
                });
            });

            // -- Catalogue: plans & coupons (operator-priced) -------------------
            Route::prefix('plans')->name('plans.')->group(function (): void {
                Route::get('/', [PlanController::class, 'index'])->name('index');
                Route::post('/', [PlanController::class, 'store'])->name('store');
                Route::match(['put', 'patch'], '{plan}', [PlanController::class, 'update'])->name('update');
                Route::delete('{plan}', [PlanController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('coupons')->name('coupons.')->group(function (): void {
                Route::get('/', [CouponController::class, 'index'])->name('index');
                Route::post('/', [CouponController::class, 'store'])->name('store');
                Route::match(['put', 'patch'], '{coupon}', [CouponController::class, 'update'])->name('update');
                Route::delete('{coupon}', [CouponController::class, 'destroy'])->name('destroy');
            });

            // -- Billing operations ---------------------------------------------
            //
            // Reading the ledger and moving money are separate permissions:
            // platform.revenue.view / platform.invoices.view for the former,
            // platform.billing.manage for anything that refunds, settles or
            // extends. Each controller enforces its own; see
            // Billing\Concerns\ManagesPlatformBilling.
            Route::get('revenue', [PlatformRevenueController::class, 'index'])->name('revenue.index');

            Route::prefix('invoices')->name('invoices.')->group(function (): void {
                Route::get('/', [PlatformInvoiceController::class, 'index'])->name('index');
                Route::get('{invoice}', [PlatformInvoiceController::class, 'show'])->name('show');
                Route::get('{invoice}/download', [PlatformInvoiceController::class, 'download'])->name('download');
            });

            Route::prefix('dunning')->name('dunning.')->group(function (): void {
                Route::get('/', [PlatformDunningController::class, 'index'])->name('index');
                Route::post('invoices/{invoice}/settle', [PlatformDunningController::class, 'settle'])->name('settle');
                Route::post('subscriptions/{subscription}/grace', [PlatformDunningController::class, 'extendGrace'])->name('grace');
                Route::post('transactions/{transaction}/refund', [PlatformDunningController::class, 'refund'])->name('refund');
            });

            Route::prefix('gateways')->name('gateways.')->group(function (): void {
                Route::get('/', [PlatformGatewayController::class, 'index'])->name('index');
                Route::post('reorder', [PlatformGatewayController::class, 'reorder'])->name('reorder');
                Route::match(['put', 'patch'], '{gateway}', [PlatformGatewayController::class, 'update'])->name('update');
                Route::post('{gateway}/test', [PlatformGatewayController::class, 'test'])->name('test');
            });

            Route::prefix('billing-events')->name('billing.events.')->group(function (): void {
                Route::get('/', [PlatformWebhookEventController::class, 'index'])->name('index');
                Route::post('{event}/replay', [PlatformWebhookEventController::class, 'replay'])->name('replay');
            });

            // -- Panel appearance -----------------------------------------------
            Route::prefix('appearance')->name('appearance.')->group(function (): void {
                Route::get('/', [PlatformAppearanceController::class, 'index'])->name('index');
                Route::put('/', [PlatformAppearanceController::class, 'update'])->name('update');
            });

            // -- The public site's pages ----------------------------------------
            //
            // Named to mirror the tenant CMS one-for-one under `admin.cms.`, which
            // is what lets the React editor swap a route-name prefix instead of
            // branching on which panel it is rendering in.
            Route::name('cms.')->group(function (): void {
                Route::prefix('pages')->name('pages.')->group(function (): void {
                    Route::get('/', [PlatformPageController::class, 'index'])->name('index');
                    Route::get('create', [PlatformPageController::class, 'create'])->name('create');
                    Route::post('/', [PlatformPageController::class, 'store'])->name('store');

                    Route::get('{page}/edit', [PlatformPageController::class, 'edit'])->name('edit');
                    Route::match(['put', 'patch'], '{page}', [PlatformPageController::class, 'update'])->name('update');
                    Route::post('{page}/duplicate', [PlatformPageController::class, 'duplicate'])->name('duplicate');
                    Route::delete('{page}', [PlatformPageController::class, 'destroy'])->name('destroy');

                    Route::post('{page}/publish', [PlatformPagePublishController::class, 'store'])->name('publish');
                    Route::delete('{page}/publish', [PlatformPagePublishController::class, 'destroy'])->name('unpublish');
                    Route::post('{page}/schedule', [PlatformPagePublishController::class, 'schedule'])->name('schedule');

                    Route::post('{page}/preview', [PlatformPagePreviewController::class, 'store'])->name('preview.create');
                    Route::get('{page}/preview', [PlatformPagePreviewController::class, 'show'])
                        ->middleware('signed')
                        ->name('preview');

                    Route::post('{page}/blocks', [PlatformPageBlockController::class, 'store'])->name('blocks.store');
                    Route::post('{page}/blocks/reorder', [PlatformPageBlockController::class, 'reorder'])->name('blocks.reorder');
                });

                Route::prefix('blocks')->name('blocks.')->group(function (): void {
                    Route::match(['put', 'patch'], '{block}', [PlatformPageBlockController::class, 'update'])->name('update');
                    Route::post('{block}/toggle', [PlatformPageBlockController::class, 'toggle'])->name('toggle');
                    Route::delete('{block}', [PlatformPageBlockController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('menus')->name('menus.')->group(function (): void {
                    Route::get('/', [PlatformMenuController::class, 'index'])->name('index');
                    Route::post('/', [PlatformMenuController::class, 'store'])->name('store');
                    Route::match(['put', 'patch'], '{menu}', [PlatformMenuController::class, 'update'])->name('update');
                    Route::delete('{menu}', [PlatformMenuController::class, 'destroy'])->name('destroy');

                    Route::post('{menu}/items', [PlatformMenuItemController::class, 'store'])->name('items.store');
                    Route::post('{menu}/items/reorder', [PlatformMenuItemController::class, 'reorder'])->name('items.reorder');
                });

                Route::prefix('menu-items')->name('menu-items.')->group(function (): void {
                    Route::match(['put', 'patch'], '{item}', [PlatformMenuItemController::class, 'update'])->name('update');
                    Route::delete('{item}', [PlatformMenuItemController::class, 'destroy'])->name('destroy');
                });
            });

            Route::prefix('blog')->name('blog.')->group(function (): void {
                Route::prefix('posts')->name('posts.')->group(function (): void {
                    Route::get('/', [PlatformPostController::class, 'index'])->name('index');
                    Route::get('create', [PlatformPostController::class, 'create'])->name('create');
                    Route::post('/', [PlatformPostController::class, 'store'])->name('store');
                    Route::get('{post}/edit', [PlatformPostController::class, 'edit'])->name('edit');
                    Route::match(['put', 'patch'], '{post}', [PlatformPostController::class, 'update'])->name('update');
                    Route::delete('{post}', [PlatformPostController::class, 'destroy'])->name('destroy');
                    Route::post('{post}/duplicate', [PlatformPostController::class, 'duplicate'])->name('duplicate');

                    Route::post('{post}/publish', [PlatformPostPublishController::class, 'store'])->name('publish');
                    Route::post('{post}/schedule', [PlatformPostPublishController::class, 'schedule'])->name('schedule');
                    Route::delete('{post}/publish', [PlatformPostPublishController::class, 'destroy'])->name('unpublish');
                    Route::post('{post}/archive', [PlatformPostPublishController::class, 'archive'])->name('archive');
                });

                Route::prefix('categories')->name('categories.')->group(function (): void {
                    Route::get('/', [PlatformCategoryController::class, 'index'])->name('index');
                    Route::post('/', [PlatformCategoryController::class, 'store'])->name('store');
                    Route::match(['put', 'patch'], '{category}', [PlatformCategoryController::class, 'update'])->name('update');
                    Route::delete('{category}', [PlatformCategoryController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('tags')->name('tags.')->group(function (): void {
                    Route::get('/', [PlatformTagController::class, 'index'])->name('index');
                    Route::post('/', [PlatformTagController::class, 'store'])->name('store');
                    Route::match(['put', 'patch'], '{tag}', [PlatformTagController::class, 'update'])->name('update');
                    Route::delete('{tag}', [PlatformTagController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('comments')->name('comments.')->group(function (): void {
                    Route::get('/', [PlatformCommentController::class, 'index'])->name('index');
                    Route::post('moderate', [PlatformCommentController::class, 'moderate'])->name('moderate');
                    Route::delete('{comment}', [PlatformCommentController::class, 'destroy'])->name('destroy');
                });
            });

            Route::prefix('media')->name('media.')->group(function (): void {
                Route::get('/', [PlatformMediaController::class, 'index'])->name('index');

                Route::post('upload', PlatformMediaUploadController::class)->name('upload');
                Route::post('bulk', [PlatformMediaController::class, 'bulk'])->name('bulk');

                Route::prefix('folders')->name('folders.')->group(function (): void {
                    Route::post('/', [PlatformMediaFolderController::class, 'store'])->name('store');
                    Route::match(['put', 'patch'], '{folder}', [PlatformMediaFolderController::class, 'update'])->name('update');
                    Route::delete('{folder}', [PlatformMediaFolderController::class, 'destroy'])->name('destroy');
                });

                Route::get('{asset}', [PlatformMediaController::class, 'show'])->name('show');
                Route::match(['put', 'patch'], '{asset}', [PlatformMediaController::class, 'update'])->name('update');
                Route::delete('{asset}', [PlatformMediaController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('seo')->name('seo.')->group(function (): void {
                Route::get('/', [PlatformSeoController::class, 'index'])->name('index');

                Route::get('settings', [PlatformSeoController::class, 'edit'])->name('settings.edit');
                Route::match(['put', 'patch'], 'settings', [PlatformSeoController::class, 'update'])->name('settings.update');
            });
        });
    });
