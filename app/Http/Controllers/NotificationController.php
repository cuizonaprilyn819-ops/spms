<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()
                ->notifications()
                ->latest()
                ->paginate(15),
        ]);
    }

    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 403);

        $notification->markAsRead();

        return redirect(\App\Support\NotificationUrl::resolve($notification->data['url'] ?? null));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('status', 'All notifications marked as read.');
    }
}
