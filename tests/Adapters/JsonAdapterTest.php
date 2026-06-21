<?php

namespace AnourValar\LaravelInterpreter\Tests\Adapters;

use AnourValar\LaravelInterpreter\Adapters\JsonAdapter;
use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class JsonAdapterTest extends AbstractSuite
{
    /**
     * @var \AnourValar\LaravelInterpreter\Adapters\JsonAdapter
     */
    protected JsonAdapter $adapter;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new JsonAdapter();
    }

    public function test_export_produces_pretty_unescaped_json(): void
    {
        $result = $this->adapter->export(['Hello world' => 'Привет мир', 'url' => 'a/b']);

        // pretty print
        $this->assertStringContainsString("\n", $result);
        $this->assertStringContainsString('    ', $result);
        // unescaped unicode
        $this->assertStringContainsString('Привет мир', $result);
        // unescaped slashes
        $this->assertStringContainsString('a/b', $result);
        // trailing newline
        $this->assertStringEndsWith("\n", $result);
    }

    public function test_import_decodes_json(): void
    {
        $result = $this->adapter->import('{"Hello world": "Привет мир"}');

        $this->assertSame(['Hello world' => 'Привет мир'], $result);
    }

    public function test_round_trip(): void
    {
        $data = ['Hello world' => 'Привет мир', 'Bye' => 'Пока'];

        $this->assertSame($data, $this->adapter->import($this->adapter->export($data)));
    }
}
