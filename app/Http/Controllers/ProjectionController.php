<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Support\Projection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance ▸ Projection: the month-by-month cash flow and its graph (see
 * {@see Projection}). The view someone set up — the range, the wallets, whether
 * unreconciled past rows count — is remembered per user: a visit without settings in
 * the URL uses their last ones. The range is kept **relative** to the current month,
 * so "three months back to a year ahead" rolls forward with time.
 */
class ProjectionController extends Controller
{
    /**
     * Saved settings as a well-formed set (defaults fill what is missing or odd).
     *
     * @param  array<string, mixed>  $saved
     * @return array{back: int, ahead: int, wallets: list<int>|null, pending: bool}
     */
    private static function settings(array $saved): array
    {
        $wallets = $saved['wallets'] ?? null;

        return [
            'back' => (int) ($saved['back'] ?? 3),
            'ahead' => (int) ($saved['ahead'] ?? 12),
            'wallets' => is_array($wallets) && $wallets !== [] ? array_values(array_map('intval', $wallets)) : null,
            'pending' => (bool) ($saved['pending'] ?? true),
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $settings = self::settings((array) data_get($user->preferences, 'projection', []));

        if ($request->hasAny(['back', 'ahead', 'wallets', 'pending'])) {
            $data = $request->validate([
                'back' => ['nullable', 'integer', 'between:0,60'],
                'ahead' => ['nullable', 'integer', 'between:0,60'],
                'wallets' => ['nullable', 'array'],
                'wallets.*' => ['integer'],
                'pending' => ['nullable', 'boolean'],
            ]);
            $settings = self::settings([
                'back' => $data['back'] ?? $settings['back'],
                'ahead' => $data['ahead'] ?? $settings['ahead'],
                'wallets' => $data['wallets'] ?? null,
                'pending' => $request->has('pending') ? $request->boolean('pending') : $settings['pending'],
            ]);

            $preferences = (array) $user->preferences;
            $preferences['projection'] = $settings;
            $user->forceFill(['preferences' => $preferences])->save();
        }

        return Inertia::render('projection/index', self::props($settings));
    }

    /**
     * The projection for a set of settings — also what Home shows a short version of.
     *
     * @param  array{back: int, ahead: int, wallets: list<int>|null, pending: bool}  $settings
     * @return array<string, mixed>
     */
    public static function props(array $settings): array
    {
        $now = Carbon::today()->startOfMonth();
        $wallets = Wallet::query()->orderBy('name')->get(['id', 'name']);
        // Wallets since deleted drop out of a saved selection.
        $chosen = $settings['wallets'] !== null
            ? array_values(array_intersect($settings['wallets'], $wallets->pluck('id')->all()))
            : null;
        if ($chosen === []) {
            $chosen = null;
        }

        return [
            'rows' => Projection::monthly(
                $now->copy()->subMonths($settings['back'])->format('Y-m'),
                $now->copy()->addMonths($settings['ahead'])->format('Y-m'),
                $chosen,
                $settings['pending'],
            ),
            'settings' => [...$settings, 'wallets' => $chosen],
            'wallets' => $wallets,
            'current_month' => $now->format('Y-m'),
        ];
    }
}
