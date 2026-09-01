<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only management of company users and their area access. Gated by the
 * `can:admin` middleware on the routes.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'is_admin',
                'can_access_finance',
                'can_access_crm',
                'is_active',
            ]);

        return Inertia::render('settings/users/index', [
            'users' => $users,
        ]);
    }

    /**
     * Invite a new company user. Creates the account with the chosen access, then
     * issues a password-broker token and returns a set-password link the admin can
     * send however they like (no reliance on outbound email). The invitee follows
     * the link to the standard reset-password page and sets their password.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'is_admin' => ['boolean'],
            'can_access_finance' => ['boolean'],
            'can_access_crm' => ['boolean'],
        ]);

        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::password(32)),
            'is_admin' => $data['is_admin'] ?? false,
            'can_access_finance' => $data['can_access_finance'] ?? false,
            'can_access_crm' => $data['can_access_crm'] ?? false,
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
            'can_access_finance' => ['required', 'boolean'],
            'can_access_crm' => ['required', 'boolean'],
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
