<?php

namespace AnourValar\LaravelInterpreter\Sources;

interface SourceInterface
{
    /**
     * Retrieve data for export
     *
     * @param array $schema
     * @return array
     */
    public function extract(array $schema): array;
}
