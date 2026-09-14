<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only management of company users. Lives in the Configuration area and is
 * gated by `can:admin`. Every active user can already use the whole app; the only
 * per-user distinctions are the admin flag (who can manage users) and the active
 * switch (deactivating locks someone out).
 */
class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_admin', 'is_active']);

        return Inertia::render('users/index', [
            'users' => $users,
        ]);
    }

    /**
     * Invite a new company user. Creates the account, then issues a password-broker
     * token and returns a set-password link the admin can send however they like
     * (no reliance on outbound email). The invitee follows the link to the standard
     * reset-password page and sets their password.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'is_admin' => ['boolean'],
        ]);

        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::password(32)),
            'is_admin' => $data['is_admin'] ?? false,
            'is_active' => true,
        ])->save();

        $token = Password::broker()->createToken($user);
        $link = route('password.reset', ['token' => $token, 'email' => $user->email]);

        Inertia::flash('invite_link', $link);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('User invited. Copy the link below to send them.')]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_admin' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        // An admin can never strip their own admin rights or deactivate
        // themselves — that would be a lock-out. Force those two to stay true.
        if ($user->is($request->user())) {
            $data['is_admin'] = true;
            $data['is_active'] = true;
        }

        $user->forceFill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access updated.')]);

        return back();
    }
}
