<?php

namespace App\Providers;

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
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, function ($event) {
            if ($event->channel !== 'database' || !config('spms.email_notifications')) return;
            // Database delivery succeeds independently of optional email delivery.
            try {
                \App\Jobs\EmailStoredNotification::dispatch($event->notification->id)->onConnection('database')->afterCommit();
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Email notification could not be queued; dashboard notification is available.', ['notification_id' => $event->notification->id]);
            }
        });
        // Share a fresh count within this request, not across users or requests.
        \Illuminate\Support\Facades\View::composer(['layouts.navigation', 'components.account-menu'], function ($view) {
            $request = request();
            if (! $request->attributes->has('navigationUnreadCount')) {
                $request->attributes->set('navigationUnreadCount', $request->user()?->unreadNotifications()->count() ?? 0);
            }
            $view->with('unreadCount', $request->attributes->get('navigationUnreadCount'));
        });
        \Illuminate\Support\Facades\View::composer('components.period-reference', function ($view) {
            $request = request();
            if (! $request->attributes->has('workspaceCurrentPeriod')) {
                $request->attributes->set('workspaceCurrentPeriod', \App\Models\EvaluationCycle::query()
                    ->where('status', \App\Models\EvaluationCycle::STATUS_OPEN)
                    ->orderByDesc('opened_at')->orderByDesc('id')->first());
            }
            $view->with('currentPeriod', $request->attributes->get('workspaceCurrentPeriod'));
        });
        // Pagination in API-rendered screens should still support opening a new browser tab.
        \Illuminate\Pagination\Paginator::currentPathResolver(function () {
            return request()->routeIs('api.*')
                ? url(preg_replace('#^api/#', '', request()->path()))
                : request()->url();
        });
    }
}
