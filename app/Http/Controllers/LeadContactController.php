<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Lead contacts — additional people at a lead, nested under the lead. Gated by
 * `can:access-crm`.
 */
class LeadContactController extends Controller
{
    public function store(Request $request, Lead $lead): RedirectResponse
    {
        $contact = new LeadContact($this->validateContact($request));
        $contact->user_id = $request->user()->id;
        $lead->contacts()->save($contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact added.')]);

        return back();
    }

    public function update(Request $request, Lead $lead, LeadContact $contact): RedirectResponse
    {
        abort_unless($contact->lead_id === $lead->id, 404);

        $contact->update($this->validateContact($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return back();
    }

    public function destroy(Lead $lead, LeadContact $contact): RedirectResponse
    {
        abort_unless($contact->lead_id === $lead->id, 404);

        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'landline' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
