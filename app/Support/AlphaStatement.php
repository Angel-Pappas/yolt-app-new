<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Reads an Alpha Bank "Κινήσεις Λογαριασμού" CSV export (web banking → Export →
 * CSV): `;`-separated UTF-8, a few header lines (the closing balance among them),
 * then one row per movement, newest first:
 *
 *     Α/Α;Ημερομηνία;Αιτιολογία;Κατάστημα;Τοκισμός από;Αρ. συναλλαγής;Ποσό;Πρόσημο ποσού;
 *     3;12/11/2026;="ΕΝΤ.261112949027Ξ044";99;12/11/2026;="202611120999513601";1000,00;Π;
 *
 * The date used is the booking date (Ημερομηνία) — the day the money shows on the
 * statement — never the value date. Π (πίστωση) is money in, Χ (χρέωση) money out.
 * Each line's `position` is its order within its day, earliest first (the file
 * lists newest first, so the highest Α/Α of a day is position 1).
 */
final class AlphaStatement
{
    /**
     * @return array{balance: float|null, balance_date: string|null, lines: list<array{date: string, position: int, text: string, amount: float, reference: string, fingerprint: string}>}
     */
    public static function parse(string $contents): array
    {
        // Drop a UTF-8 byte-order mark; accept the Greek Windows code page too.
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = (string) mb_convert_encoding($contents, 'UTF-8', 'Windows-1253');
        }

        $rows = array_map(
            fn (string $line): array => array_map(self::cell(...), explode(';', $line)),
            preg_split('/\r\n|\n|\r/', $contents) ?: [],
        );

        $balance = null;
        $balanceDate = null;
        $headerAt = null;
        foreach ($rows as $i => $row) {
            $label = BankText::fold($row[0]);
            if (str_starts_with($label, BankText::fold('Νέο Μικτό Υπόλοιπο'))) {
                $balance = self::signedAmount($row[1] ?? '');
            } elseif (str_starts_with($label, BankText::fold('Ημερομηνία'))) {
                $balanceDate = self::date($row[1] ?? '');
            } elseif ($label === BankText::fold('Α/Α')) {
                $headerAt = $i;
                break;
            }
        }

        if ($headerAt === null) {
            throw new InvalidArgumentException(__('This is not an Alpha Bank statement export (CSV).'));
        }

        // Each movement: [Α/Α, date, text, branch, value date, reference, amount, sign].
        $movements = [];
        foreach (array_slice($rows, $headerAt + 1) as $row) {
            if (count($row) < 8 || ! ctype_digit($row[0])) {
                continue;
            }
            $date = self::date($row[1]);
            $amount = self::amount($row[6]);
            if ($date === null || $amount === null) {
                throw new InvalidArgumentException(__('Line :n of the statement could not be read.', ['n' => $row[0]]));
            }
            $movements[] = [
                'number' => (int) $row[0],
                'date' => $date,
                'text' => $row[2],
                'reference' => $row[5],
                'amount' => BankText::fold($row[7]) === 'Π' ? $amount : -$amount,
            ];
        }

        // Earliest first: by date, then the higher Α/Α (older) first within a day.
        usort($movements, fn (array $a, array $b): int => [$a['date'], -$a['number']] <=> [$b['date'], -$b['number']]);

        $lines = [];
        $positions = [];
        $seen = [];
        foreach ($movements as $m) {
            $positions[$m['date']] = ($positions[$m['date']] ?? 0) + 1;
            // Identity of a line across exports: what the bank says about it. A line
            // repeated word for word in one export gets a counter so both are kept.
            $base = implode('|', [$m['date'], $m['reference'], $m['text'], number_format($m['amount'], 2, '.', '')]);
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $lines[] = [
                'date' => $m['date'],
                'position' => $positions[$m['date']],
                'text' => $m['text'],
                'amount' => $m['amount'],
                'reference' => $m['reference'],
                'fingerprint' => hash('sha256', $base.'#'.$seen[$base]),
            ];
        }

        return ['balance' => $balance, 'balance_date' => $balanceDate, 'lines' => $lines];
    }

    /** A cell without spaces and Excel's `="…"` text wrapper. */
    private static function cell(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^="(.*)"$/s', $value, $m) === 1) {
            $value = $m[1];
        }

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** `16/11/2026` → `2026-11-16`; null if it isn't a date. */
    private static function date(string $value): ?string
    {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($value), $m) !== 1 || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
    }

    /** `1.234,56` or `1234,56` → 1234.56; null if it isn't an amount. */
    private static function amount(string $value): ?float
    {
        $value = str_replace(['.', ' '], '', trim($value));
        if (preg_match('/^\d+(,\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        return round((float) str_replace(',', '.', $value), 2);
    }

    /** `2915,70 Π` → 2915.70, `… Χ` → negative; null if unreadable. */
    private static function signedAmount(string $value): ?float
    {
        if (preg_match('/^([\d.,]+)\s*(\S+)?$/u', trim($value), $m) !== 1) {
            return null;
        }
        $amount = self::amount($m[1]);
        if ($amount === null) {
            return null;
        }

        return BankText::fold($m[2] ?? 'Π') === 'X' ? -$amount : $amount;
    }
}
