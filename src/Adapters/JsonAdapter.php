<?php

namespace AnourValar\LaravelInterpreter\Adapters;

class JsonAdapter implements AdapterInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\LaravelInterpreter\Adapters\AdapterInterface::export()
     */
    public function export(array $data) : string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\LaravelInterpreter\Adapters\AdapterInterface::import()
     */
    public function import(string $data) : array
    {
        return json_decode($data, true);
    }
}
