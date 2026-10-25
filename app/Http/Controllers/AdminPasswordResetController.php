<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminPasswordResetController extends Controller
{
    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()->hasRole('Administrator'), 403);
        abort_if($user->is($request->user()), 403, 'Use Profile to change your own password.');

        return view('admin.password-reset', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Administrator'), 403);
        abort_if($user->is($request->user()), 403, 'Use Profile to change your own password.');

        $data = $request->validate([
            'admin_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()->route('employees.index')->with('status', 'Password reset for '.$user->name.'. Share the new password securely.');
    }
}
