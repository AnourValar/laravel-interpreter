<?php

namespace AnourValar\LaravelInterpreter\Adapters;

interface AdapterInterface
{
    /**
     * Prepare data (serialize) for saving into export file
     *
     * @param array $data
     * @return string
     */
    public function export(array $data) : string;

    /**
     * Extract data (unserialize) from filled file
     *
     * @param string $data
     * @return array
     */
    public function import(string $data) : array;
}
