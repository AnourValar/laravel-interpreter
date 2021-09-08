<?php

namespace AnourValar\LaravelInterpreter\Helpers;

class SlugHelper
{
    /**
     * Translit string
     *
     * @param string $value
     * @return string|NULL
     */
    public function translit(?string $value): ?string
    {
        if (is_null($value)) {
            return $value;
        }

        return \Illuminate\Support\Str::ascii($value, 'en');
    }
}
