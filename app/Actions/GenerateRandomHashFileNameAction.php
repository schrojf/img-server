<?php

namespace App\Actions;

class GenerateRandomHashFileNameAction
{
    public function handle(string $extension = ''): string
    {
        // Same length of 40 characters as the sha1 hash algorithm
        $hash = bin2hex(random_bytes(20));

        // Prevent ad-blocker false positives: replace 'ad' directory segments with 'ia'.
        // Since 'i' is not a hex character, this substitution is unambiguous.
        for ($i = 0; $i < 6; $i += 2) {
            if ($hash[$i] === 'a' && $hash[$i + 1] === 'd') {
                $hash[$i] = 'i';
                $hash[$i + 1] = 'a';
            }
        }

        $l1 = $hash[0].$hash[1];
        $l2 = $hash[2].$hash[3];
        $l3 = $hash[4].$hash[5];

        $extension = ltrim($extension, '.');

        if (! empty($extension)) {
            $extension = '.'.$extension;
        }

        return $l1.DIRECTORY_SEPARATOR.$l2.DIRECTORY_SEPARATOR.$l3.DIRECTORY_SEPARATOR.$hash.$extension;
    }
}
