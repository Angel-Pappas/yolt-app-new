<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The de minimis grants the company had received by 2026-10-30, copied from the
 * table in the declaration for the PHILOXENIA–HOTELIA call (Angel, 2026-10-30), so
 * the list starts complete. Later grants are added in the app.
 */
return new class extends Migration
{
    private const BENEFICIARY = 'YOLT LABS Ι Κ Ε';

    private const VAT_NUMBER = '801487298';

    private const STARTUPS = 'Δράσεις προώθησης της επιχειρηματικότητας νεοφυών επιχειρήσεων (Φορέας: ΠΕΠ Αττικής 2014-2020)';

    private const EDIH = 'Ανάπτυξη Δικτύου των Ευρωπαϊκών Κόμβων Ψηφιακής Καινοτομίας - ΕΚΨΚ (European Digital Innovation Hubs - EDIHS) που χρηματοδοτούνται από το Πρόγραμμα «Ψηφιακή Ευρώπη» (Φορέας: European Digital Innovation Hubs – EDIHS, Πρόγραμμα Ψηφιακή Ευρώπη 2021-2027)';

    public function up(): void
    {
        $now = now();

        DB::table('de_minimis_aids')->insert(array_map(fn (array $row) => [
            'programme' => $row[0],
            'decision_number' => $row[1],
            'decision_date' => $row[2],
            'approved_amount' => $row[3],
            'paid_amount' => $row[3],
            'last_payment_date' => $row[4],
            'beneficiary_name' => self::BENEFICIARY,
            'beneficiary_vat_number' => self::VAT_NUMBER,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            [self::STARTUPS, 'PIR010 - ACC-EL300730-5062147_1_801487298', '2023-05-22', '800.00', '2023-06-20'],
            [self::STARTUPS, 'PIR010 - ACC-EL300730-5062147_2_801487298', '2023-05-22', '5000.00', '2023-06-20'],
            [self::STARTUPS, 'PIR010 - ACC-EL300730-5062147_5_801487298', '2023-05-22', '4000.00', '2023-06-20'],
            [self::STARTUPS, 'PIR010 - ACC-EL300730-5062147_9_801487298', '2023-05-22', '3500.00', '2023-06-20'],
            [self::EDIH, 'ACC-10115035-NCSRD-6002637', '2023-04-05', '21700.00', '2023-08-11'],
        ]));
    }

    public function down(): void
    {
        DB::table('de_minimis_aids')->whereIn('decision_number', [
            'PIR010 - ACC-EL300730-5062147_1_801487298',
            'PIR010 - ACC-EL300730-5062147_2_801487298',
            'PIR010 - ACC-EL300730-5062147_5_801487298',
            'PIR010 - ACC-EL300730-5062147_9_801487298',
            'ACC-10115035-NCSRD-6002637',
        ])->delete();
    }
};
