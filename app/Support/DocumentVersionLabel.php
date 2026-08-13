<?php

namespace App\Support;

final class DocumentVersionLabel
{
    public static function make(?string $jenisVersi, ?int $nomorVersi): string
    {
        if ($jenisVersi !== 'perubahan') {
            return 'Murni';
        }

        return 'Perubahan '.self::toRoman(max(1, ((int) $nomorVersi) - 1));
    }

    private static function toRoman(int $number): string
    {
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';

        foreach ($map as $value => $roman) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }
}
