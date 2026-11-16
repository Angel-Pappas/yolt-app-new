<?php

namespace App\Support;

/**
 * Comparing a bank statement's text with names. The bank mixes Greek and Latin
 * capitals that look the same ("FΑCΕΒΚ" is F + Greek Α, C, Greek Ε, …), adds
 * accents in names and appends codes that change every time ("*WRJZNE6EV4",
 * ".1442151650"). Everything here first folds look-alike Greek capitals to Latin and
 * drops accents, so the same merchant always reads the same.
 */
final class BankText
{
    /** Greek capitals that look like a Latin one. */
    private const LOOKALIKES = [
        'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K',
        'Μ' => 'M', 'Ν' => 'N', 'Ο' => 'O', 'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
    ];

    /** Accented Greek capitals and their plain letter. */
    private const ACCENTS = [
        'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ϊ' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ϋ' => 'Υ', 'Ώ' => 'Ω',
    ];

    /** Upper case, no accents, look-alike Greek folded to Latin. */
    public static function fold(string $text): string
    {
        return strtr(strtr(mb_strtoupper($text, 'UTF-8'), self::ACCENTS), self::LOOKALIKES);
    }

    /**
     * The stable part of a statement text — the merchant without the codes that
     * change each time: folded, split into words, and every word containing a digit
     * dropped ("FΑCΕΒΚ *WRJΖΝΕ6ΕV4" → "FACEBK", "ΕΞΔ.ΤΗΡ.ΛΟΓ.11/26" → "EΞΔ THP ΛOΓ").
     * Empty when nothing stable is left (a bare reference number).
     */
    public static function key(string $text): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::fold($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = array_filter($words, fn (string $w): bool => preg_match('/\d/', $w) !== 1);

        return implode(' ', $kept);
    }

    /**
     * How strongly a name appears in a statement text: the number of the name's
     * words (4+ letters) found in the folded text. 0 = no sign of it.
     */
    public static function nameScore(string $name, string $text): int
    {
        $haystack = self::fold($text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::fold($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $score = 0;
        foreach (array_unique($words) as $word) {
            if (mb_strlen($word) >= 4 && str_contains($haystack, $word)) {
                $score++;
            }
        }

        return $score;
    }

    /** Whether the text is one of the bank's own charges (ΕΞΟΔΑ …, ΕΞΔ.…). */
    public static function isBankCharge(string $text): bool
    {
        $folded = self::fold($text);

        return str_starts_with($folded, 'EΞOΔA') || str_starts_with($folded, 'EΞΔ');
    }
}
