<?php

namespace App\Providers;

use App\Jobs\EmailStoredNotification;
use App\Models\EvaluationCycle;
use App\Notifications\TargetConflictNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationSent::class, function ($event) {
            if ($event->channel !== 'database' || ! config('spms.email_notifications') || $event->notification instanceof TargetConflictNotification) {
                return;
            }
            // Database delivery succeeds independently of optional email delivery.
            try {
                EmailStoredNotification::dispatch($event->notification->id)->onConnection('database')->afterCommit();
            } catch (\Throwable $exception) {
                Log::warning('Email notification could not be queued; dashboard notification is available.', ['notification_id' => $event->notification->id]);
            }
        });
        // Share a fresh count within this request, not across users or requests.
        View::composer(['layouts.navigation', 'components.account-menu'], function ($view) {
            $request = request();
            if (! $request->attributes->has('navigationUnreadCount')) {
                $request->attributes->set('navigationUnreadCount', $request->user()?->unreadNotifications()->count() ?? 0);
            }
            $view->with('unreadCount', $request->attributes->get('navigationUnreadCount'));
        });
        View::composer('components.period-reference', function ($view) {
            $request = request();
            if (! $request->attributes->has('workspaceCurrentPeriod')) {
                $request->attributes->set('workspaceCurrentPeriod', EvaluationCycle::query()
                    ->where('status', EvaluationCycle::STATUS_OPEN)
                    ->orderByDesc('opened_at')->orderByDesc('id')->first());
            }
            $view->with('currentPeriod', $request->attributes->get('workspaceCurrentPeriod'));
        });
        // Pagination in API-rendered screens should still support opening a new browser tab.
        Paginator::currentPathResolver(function () {
            return request()->routeIs('api.*')
                ? url(preg_replace('#^api/#', '', request()->path()))
                : request()->url();
        });
    }
}
