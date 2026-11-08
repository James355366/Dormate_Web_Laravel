<?php

namespace App\Support;

use RuntimeException;

final class MobileNumber
{
    public static function normalize(string $value): string
    {
        // Local Philippine and international +63 notation identify the same account.
        $number = preg_replace('/[\s()-]+/', '', trim($value));
        if (preg_match('/^09[0-9]{9}$/D', $number)) {
            return '+63'.substr($number, 1);
        }
        if (preg_match('/^\+?639[0-9]{9}$/D', $number)) {
            return '+'.ltrim($number, '+');
        }

        throw new RuntimeException('Enter a Philippine mobile number such as 09171234567 or +639171234567.');
    }
}
