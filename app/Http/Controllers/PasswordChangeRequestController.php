<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordChangeRequested;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordChangeRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->hasRole('Administrator'), 403);

        $administrators = User::role('Administrator')->get();
        if ($administrators->isEmpty()) {
            return back()->withErrors(['passwordRequest' => 'No administrator is available. Use Forgot password to reset by email.']);
        }

        $administrators->each->notify(new PasswordChangeRequested($request->user()));

        return back()->with('status', 'password-change-requested');
    }
}
