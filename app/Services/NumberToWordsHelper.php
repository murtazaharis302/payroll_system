<?php

declare(strict_types=1);

namespace App\Services;

class NumberToWordsHelper
{
    private static array $dictionary = [
        0                   => 'Zero',
        1                   => 'One',
        2                   => 'Two',
        3                   => 'Three',
        4                   => 'Four',
        5                   => 'Five',
        6                   => 'Six',
        7                   => 'Seven',
        8                   => 'Eight',
        9                   => 'Nine',
        10                  => 'Ten',
        11                  => 'Eleven',
        12                  => 'Twelve',
        13                  => 'Thirteen',
        14                  => 'Fourteen',
        15                  => 'Fifteen',
        16                  => 'Sixteen',
        17                  => 'Seventeen',
        18                  => 'Eighteen',
        19                  => 'Nineteen',
        20                  => 'Twenty',
        30                  => 'Thirty',
        40                  => 'Forty',
        50                  => 'Fifty',
        60                  => 'Sixty',
        70                  => 'Seventy',
        80                  => 'Eighty',
        90                  => 'Ninety',
        100                 => 'Hundred',
        1000                => 'Thousand',
        1000000             => 'Million',
        1000000000          => 'Billion',
    ];

    /**
     * Convert numeric amount to spelled-out Pesos string.
     * Example: 15450.50 => "PESOS: FIFTEEN THOUSAND FOUR HUNDRED FIFTY PESOS AND 50/100 ONLY (P 15,450.50)"
     */
    public static function formatPesos(float|int $amount): string
    {
        $formattedAmount = number_format((float) $amount, 2, '.', ',');
        $words = self::toWords((float) $amount);
        return sprintf("PESOS: %s (P %s)", strtoupper($words), $formattedAmount);
    }

    /**
     * Convert float amount to words.
     */
    public static function toWords(float $number): string
    {
        if ($number < 0) {
            return 'Minus ' . self::toWords(abs($number));
        }

        $whole = (int) floor($number);
        $fraction = (int) round(($number - $whole) * 100);

        $words = self::convertWholeNumber($whole);

        if ($words === '') {
            $words = 'Zero';
        }

        $result = $words . ' Pesos';

        if ($fraction > 0) {
            $result .= sprintf(' AND %02d/100', $fraction);
        } else {
            $result .= ' ONLY';
        }

        return $result;
    }

    private static function convertWholeNumber(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        if ($number < 21) {
            return self::$dictionary[$number];
        }

        if ($number < 100) {
            $tens = ((int) ($number / 10)) * 10;
            $units = $number % 10;
            return self::$dictionary[$tens] . ($units ? ' ' . self::$dictionary[$units] : '');
        }

        if ($number < 1000) {
            $hundreds = (int) ($number / 100);
            $remainder = $number % 100;
            return self::$dictionary[$hundreds] . ' Hundred' . ($remainder ? ' ' . self::convertWholeNumber($remainder) : '');
        }

        if ($number < 1000000) {
            $thousands = (int) ($number / 1000);
            $remainder = $number % 1000;
            return self::convertWholeNumber($thousands) . ' Thousand' . ($remainder ? ' ' . self::convertWholeNumber($remainder) : '');
        }

        if ($number < 1000000000) {
            $millions = (int) ($number / 1000000);
            $remainder = $number % 1000000;
            return self::convertWholeNumber($millions) . ' Million' . ($remainder ? ' ' . self::convertWholeNumber($remainder) : '');
        }

        $billions = (int) ($number / 1000000000);
        $remainder = $number % 1000000000;
        return self::convertWholeNumber($billions) . ' Billion' . ($remainder ? ' ' . self::convertWholeNumber($remainder) : '');
    }
}
