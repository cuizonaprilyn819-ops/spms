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
        $notifications = $request->user()->notifications()->latest()->paginate(15);
        $movIds = $notifications->getCollection()->where('type', \App\Notifications\TargetMovSubmittedNotification::class)
            ->map(fn ($notification) => $notification->data['mov_id'] ?? null)->filter()->all();
        $movs = \App\Models\TargetMov::with(['employee', 'target'])->whereIn('id', $movIds)->get()->keyBy('id');
        foreach ($notifications as $notification) {
            if ($notification->type !== \App\Notifications\TargetMovSubmittedNotification::class) continue;
            $mov = $movs->get($notification->data['mov_id'] ?? null);
            if ($mov && $mov->employee && $mov->target) {
                // Refresh display text for older notifications without rewriting history/read status.
                $notification->data = [...$notification->data, ...(new \App\Notifications\TargetMovSubmittedNotification($mov, $notification->data['mov_ids'] ?? []))->toArray($request->user())];
            }
        }
        return view('notifications.index', [
            'notifications' => $notifications,
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
