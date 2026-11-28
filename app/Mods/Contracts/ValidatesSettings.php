<?php

namespace App\Mods\Contracts;

interface ValidatesSettings
{
    /**
     * Cross-checks submitted Party settings before they are saved.
     *
     * @param  array<string, mixed>  $submitted  Normalised values keyed by setting code.
     * @return array<string, string> Error message keyed by setting code.
     */
    public function validateSettings(array $submitted): array;
}
