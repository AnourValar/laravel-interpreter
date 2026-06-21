<?php

namespace AnourValar\LaravelInterpreter\Tests\Adapters;

use AnourValar\LaravelInterpreter\Adapters\CsvAdapter;
use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class CsvAdapterTest extends AbstractSuite
{
    /**
     * @var \AnourValar\LaravelInterpreter\Adapters\CsvAdapter
     */
    protected CsvAdapter $adapter;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new CsvAdapter();
    }

    public function test_export_uses_semicolon_delimiter_and_cp1251(): void
    {
        $result = $this->adapter->export(['Hello' => 'World']);

        $this->assertStringContainsString('Hello;World', $result);
    }

    public function test_export_encodes_cyrillic_to_cp1251(): void
    {
        $result = $this->adapter->export(['Привет' => 'Мир']);

        // The stored bytes must be cp1251, not utf-8.
        $this->assertStringContainsString(iconv('utf-8', 'cp1251', 'Привет'), $result);
        $this->assertStringNotContainsString('Привет', $result);
    }

    public function test_import_decodes_cp1251_back_to_utf8(): void
    {
        $csv = iconv('utf-8', 'cp1251', "Привет;Мир");

        $result = $this->adapter->import($csv);

        $this->assertArrayHasKey('Привет', $result);
        $this->assertSame('Мир', $result['Привет']);
    }

    public function test_round_trip_preserves_cyrillic_values(): void
    {
        $data = ['Hello world' => 'Привет мир', 'Bye' => 'Пока'];

        $result = $this->adapter->import($this->adapter->export($data));

        $this->assertSame('Привет мир', $result['Hello world']);
        $this->assertSame('Пока', $result['Bye']);
    }
}
