<?php

namespace AnourValar\LaravelInterpreter\Services;

class ImportService
{
    /**
     * Save translation to the file
     *
     * @param string $path
     * @param array $data
     * @param string $chmod
     * @return bool
     */
    public function save(string $path, array $data, string $chmod = '0755'): bool
    {
        if (preg_match('#\.php$#i', $path)) {
            $array = $this->exportArray($data, 4);

            $data = file_get_contents(__DIR__.'/../resources/template.tpl');
            $data = str_replace('%PASTE HERE%', $array, $data);
        } else {
            $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), $chmod, true);
        }

        return (file_put_contents($path, $data) !== false);
    }

    /**
     * Inline code for the array
     *
     * @param array $array
     * @param int $indentSize
     * @param bool $forceNewLine
     * @return string
     */
    private function exportArray(array $array, int $indentSize = 4, bool $forceNewLine = false): string
    {
        $result = '';

        foreach ($array as $key => $value) {
            if ($result) {
                $result .= "\n";
            }

            $key = "'".addcslashes($key, "'")."'";

            if (is_array($value)) {
                $result .= str_pad('', $indentSize, ' ', STR_PAD_LEFT) . "$key => [";

                $sub = $this->exportArray($value, $indentSize + 4, $forceNewLine);

                if ($sub) {
                    if (stripos($sub, "\n") || $forceNewLine) {
                        $result .= "\n" . $sub . "\n" . str_pad('', $indentSize, ' ', STR_PAD_LEFT) . "],";
                    } else {
                        $result .= trim(mb_substr($sub, 0, -1))."],";
                    }
                } else {
                    $result .= "],";
                }
            } else {
                if (is_null($value)) {
                    $value = 'null';
                } elseif ($value === true) {
                    $value = 'true';
                } elseif ($value === false) {
                    $value = 'false';
                } elseif (is_string($value)) {
                    $value = "'".addcslashes($value, "'")."'";
                }

                $result .= str_pad('', $indentSize, ' ', STR_PAD_LEFT) . "$key => $value,";
            }
        }

        return $result;
    }
}
