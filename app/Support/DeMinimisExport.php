<?php

namespace App\Support;

use App\Models\DeMinimisAid;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The de minimis list as a downloadable file — Markdown or Excel. Both use the
 * column headings of the official "Πίνακας Επιχορηγήσεων de minimis" that grant
 * applications ask for (so the file can be copied straight into one), the app's
 * Greek number/date style, and a closing total row.
 */
class DeMinimisExport
{
    /** The official table's headings, in its column order. */
    public const HEADINGS = [
        '#',
        'Πρόγραμμα / Μέτρο / Δράση',
        'Αριθμός Απόφασης Ένταξης ή Σύμβασης',
        'Ημ/νία Απόφασης Ένταξης',
        'Ποσό δημόσιας χρηματοδότησης (απόφαση ένταξης)',
        'Ποσό Δημόσιας χρηματοδότησης',
        'Ημ/νία καταβολής τελευταίας χρηματοδότησης',
        'Επωνυμία δικαιούχου',
        'ΑΦΜ δικαιούχου',
    ];

    public const TOTAL_LABEL = 'Σύνολο';

    /** Excel's number format for euro amounts — shown `1.234,56 €` in a Greek locale. */
    private const EURO_FORMAT = '#,##0.00 "€"';

    private const DATE_FORMAT = 'dd/mm/yyyy';

    /**
     * Every grant, oldest decision first (the order the official table lists them).
     *
     * @return Collection<int, DeMinimisAid>
     */
    public static function rows(): Collection
    {
        return DeMinimisAid::query()->orderBy('decision_date')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, DeMinimisAid>  $aids
     */
    public static function markdown(Collection $aids): string
    {
        $lines = [
            '# De minimis',
            '',
            self::markdownRow(self::HEADINGS),
            self::markdownRow(array_fill(0, count(self::HEADINGS), '---')),
        ];

        foreach ($aids->values() as $i => $aid) {
            $lines[] = self::markdownRow([
                (string) ($i + 1),
                $aid->programme,
                $aid->decision_number,
                $aid->decision_date->format('d/m/Y'),
                self::euro($aid->approved_amount),
                self::euro($aid->paid_amount),
                $aid->last_payment_date?->format('d/m/Y') ?? '',
                $aid->beneficiary_name,
                $aid->beneficiary_vat_number,
            ]);
        }

        $lines[] = self::markdownRow([
            '',
            '**'.self::TOTAL_LABEL.'**',
            '',
            '',
            '**'.self::euro((string) $aids->sum(fn (DeMinimisAid $a) => (float) $a->approved_amount)).'**',
            '**'.self::euro((string) $aids->sum(fn (DeMinimisAid $a) => (float) $a->paid_amount)).'**',
            '',
            '',
            '',
        ]);

        return implode("\n", $lines)."\n";
    }

    /**
     * Writes the list as an .xlsx workbook to `$path`: real numbers and dates (so
     * Excel can sum and sort them), formatted the Greek way, with a total row.
     *
     * @param  Collection<int, DeMinimisAid>  $aids
     */
    public static function xlsx(Collection $aids, string $path): void
    {
        $writer = new Writer;
        $options = $writer->getOptions();
        foreach ([6, 60, 42, 14, 20, 20, 18, 22, 14] as $column => $width) {
            $options->setColumnWidth($width, $column + 1);
        }
        $writer->openToFile($path);

        $bold = (new Style)->withFontBold(true);
        $wrap = (new Style)->withShouldWrapText(true);
        $euro = (new Style)->withFormat(self::EURO_FORMAT);
        $date = (new Style)->withFormat(self::DATE_FORMAT);

        $writer->addRow(Row::fromValuesWithStyle(self::HEADINGS, $bold->withShouldWrapText(true)));

        foreach ($aids->values() as $i => $aid) {
            $writer->addRow(new Row([
                Cell::fromValue($i + 1),
                Cell::fromValue($aid->programme, $wrap),
                Cell::fromValue($aid->decision_number),
                Cell::fromValue($aid->decision_date->toDateTimeImmutable(), $date),
                Cell::fromValue((float) $aid->approved_amount, $euro),
                Cell::fromValue($aid->paid_amount === null ? null : (float) $aid->paid_amount, $euro),
                Cell::fromValue($aid->last_payment_date?->toDateTimeImmutable(), $date),
                Cell::fromValue($aid->beneficiary_name),
                Cell::fromValue($aid->beneficiary_vat_number),
            ]));
        }

        $boldEuro = $euro->withFontBold(true);
        $writer->addRow(new Row([
            Cell::fromValue(null),
            Cell::fromValue(self::TOTAL_LABEL, $bold),
            Cell::fromValue(null),
            Cell::fromValue(null),
            Cell::fromValue(round($aids->sum(fn (DeMinimisAid $a) => (float) $a->approved_amount), 2), $boldEuro),
            Cell::fromValue(round($aids->sum(fn (DeMinimisAid $a) => (float) $a->paid_amount), 2), $boldEuro),
        ]));

        $writer->close();
    }

    /** `1234.5` → `1.234,50 €`; blank when there is no amount. */
    public static function euro(?string $amount): string
    {
        return $amount === null ? '' : number_format((float) $amount, 2, ',', '.').' €';
    }

    /**
     * @param  list<string>  $cells
     */
    private static function markdownRow(array $cells): string
    {
        // A `|` inside a value would split the cell; line breaks would end the row.
        $escaped = array_map(
            fn (string $cell) => str_replace(['|', "\r\n", "\n"], ['\|', ' ', ' '], $cell),
            $cells,
        );

        return '| '.implode(' | ', $escaped).' |';
    }
}
