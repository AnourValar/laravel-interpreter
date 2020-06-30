<?php

namespace AnourValar\LaravelInterpreter\Adapters;

class CsvAdapter implements AdapterInterface
{
    /**
     * @var string
     */
    protected $delimiter = ';';

    /**
     * {@inheritDoc}
     * @see \AnourValar\LaravelInterpreter\Adapters\AdapterInterface::export()
     */
    public function export(array $data) : string
    {
        ob_start();
        $resource = fopen('php://output', 'w');

        foreach ($data as $key => $value) {
            fputcsv($resource, [iconv('utf-8', 'cp1251', $key), iconv('utf-8', 'cp1251', $value)], $this->delimiter);
        }

        fclose($resource);

        return ob_get_clean();
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\LaravelInterpreter\Adapters\AdapterInterface::import()
     */
    public function import(string $data) : array
    {
        $result = [];

        foreach (explode("\n", $data) as $item) {
            $item = trim($item);
            $item = str_getcsv($item, $this->delimiter);

            $key = iconv('cp1251', 'utf-8', ($item[0] ?? ''));
            $value = iconv('cp1251', 'utf-8', ($item[1] ?? ''));

            $result[$key] = $value;
        }

        return $result;
    }
}
