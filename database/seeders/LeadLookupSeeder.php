<?php

namespace Database\Seeders;

use App\Models\LeadOrigin;
use App\Models\LeadStatus;
use App\Models\ProjectStatus;
use Illuminate\Database\Seeder;

/**
 * Seeds the default CRM lookup lists — lead pipeline stages, lead origins, and the
 * project lifecycle stages. Idempotent (keyed on name), so it is safe to run
 * against an existing database without creating duplicates.
 */
class LeadLookupSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['New', 'Contacted', 'Follow-up', 'Proposal', 'Won', 'Lost'];
        foreach ($statuses as $position => $name) {
            LeadStatus::query()->firstOrCreate(['name' => $name], ['position' => $position]);
        }

        // The two done-states: "Project Agreed" is a normal manual stage a
        // salesperson picks; "Converted" is the flagged state the Convert action
        // sets (hidden from the default list and manual editors).
        LeadStatus::query()->firstOrCreate(['name' => 'Project Agreed'], ['position' => 100, 'is_conversion' => false]);
        LeadStatus::query()->firstOrCreate(['name' => 'Converted'], ['position' => 101, 'is_conversion' => true]);

        $origins = ['Campaign', 'Ads', 'Expo', 'Referral', 'Website'];
        foreach ($origins as $position => $name) {
            LeadOrigin::query()->firstOrCreate(['name' => $name], ['position' => $position]);
        }

        $projectStatuses = ['Agreed', 'Scoping', 'Contracting', 'In progress', 'Delivered', 'Cancelled'];
        foreach ($projectStatuses as $position => $name) {
            ProjectStatus::query()->firstOrCreate(['name' => $name], ['position' => $position]);
        }
    }
}
