<?php

namespace AnourValar\LaravelInterpreter\Helpers;

class SlugHelper extends \Illuminate\Support\Str
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

        foreach (static::charsArray() as $key => $val) {
            $value = str_replace($val, $key, $value);
        }

        return $value;
    }
}
