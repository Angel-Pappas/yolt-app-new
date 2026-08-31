<?php

namespace Database\Seeders;

use App\Models\LeadOrigin;
use App\Models\LeadStatus;
use Illuminate\Database\Seeder;

/**
 * Seeds the default lead pipeline stages and origins. Idempotent (keyed on name),
 * so it is safe to run against an existing database without creating duplicates.
 */
class LeadLookupSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['New', 'Contacted', 'Follow-up', 'Proposal', 'Won', 'Lost'];
        foreach ($statuses as $position => $name) {
            LeadStatus::query()->firstOrCreate(['name' => $name], ['position' => $position]);
        }

        $origins = ['Campaign', 'Ads', 'Expo', 'Referral', 'Website'];
        foreach ($origins as $position => $name) {
            LeadOrigin::query()->firstOrCreate(['name' => $name], ['position' => $position]);
        }
    }
}
