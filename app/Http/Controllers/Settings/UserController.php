<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
