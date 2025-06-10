<?php

namespace App\Actions;

class GenerateRandomHashFileNameAction
{
    public function handle(string $extension = ''): string
    {
        // Same length of 40 characters as the sha1 hash algorithm
        $hash = bin2hex(random_bytes(20));

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
