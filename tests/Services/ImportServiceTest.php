<?php

namespace AnourValar\LaravelInterpreter\Tests\Services;

use AnourValar\LaravelInterpreter\Services\ImportService;
use AnourValar\LaravelInterpreter\Tests\AbstractSuite;

class ImportServiceTest extends AbstractSuite
{
    /**
     * @var \AnourValar\LaravelInterpreter\Services\ImportService
     */
    protected ImportService $service;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImportService();
    }

    public function test_it_saves_a_php_file_through_the_template(): void
    {
        $path = $this->basePath . '/out/messages.php';

        $this->assertTrue($this->service->save($path, ['hello' => 'Привет', 'bye' => 'Пока']));
        $this->assertFileExists($path);

        // The saved file must be a valid php array.
        $data = require $path;
        $this->assertSame(['hello' => 'Привет', 'bye' => 'Пока'], $data);

        // It must use the package template (opening tag + return).
        $contents = file_get_contents($path);
        $this->assertStringStartsWith('<?php', $contents);
        $this->assertStringContainsString('return [', $contents);
    }

    public function test_it_saves_nested_php_arrays_with_indentation(): void
    {
        $path = $this->basePath . '/out/nested.php';

        $value = ['auth' => ['failed' => 'Неверно', 'rules' => ['min' => 'Мало', 'max' => 'Много']]];
        $this->service->save($path, $value);

        $data = require $path;
        $this->assertSame($value, $data);

        $contents = file_get_contents($path);
        $this->assertStringContainsString("    'auth' => [", $contents);
        $this->assertStringContainsString("        'failed' => 'Неверно',", $contents);
        $this->assertStringContainsString("            'min' => 'Мало',", $contents);
    }

    public function test_it_inlines_single_key_nested_arrays(): void
    {
        $path = $this->basePath . '/out/inline.php';

        $value = ['nested' => ['deep' => 'Глубоко']];
        $this->service->save($path, $value);

        $contents = file_get_contents($path);
        $this->assertStringContainsString("'nested' => ['deep' => 'Глубоко'],", $contents);
        $this->assertSame($value, require $path);
    }

    public function test_it_escapes_single_quotes_in_php_output(): void
    {
        $path = $this->basePath . '/out/quotes.php';

        $this->service->save($path, ["it's" => "value 'x'"]);

        $data = require $path;
        $this->assertSame(["it's" => "value 'x'"], $data);
    }

    public function test_it_saves_scalar_types_in_php_output(): void
    {
        $path = $this->basePath . '/out/scalars.php';

        $this->service->save($path, ['n' => null, 't' => true, 'f' => false, 'i' => 5]);

        $contents = file_get_contents($path);
        $this->assertStringContainsString("'n' => null,", $contents);
        $this->assertStringContainsString("'t' => true,", $contents);
        $this->assertStringContainsString("'f' => false,", $contents);
        $this->assertStringContainsString("'i' => 5,", $contents);

        $this->assertSame(['n' => null, 't' => true, 'f' => false, 'i' => 5], require $path);
    }

    public function test_it_saves_a_json_file(): void
    {
        $path = $this->basePath . '/out/messages.json';

        $this->assertTrue($this->service->save($path, ['Hello world' => 'Привет мир']));

        $contents = file_get_contents($path);
        $this->assertStringContainsString('Привет мир', $contents); // unescaped unicode
        $this->assertStringEndsWith("\n", $contents);
        $this->assertSame(['Hello world' => 'Привет мир'], json_decode($contents, true));
    }

    public function test_it_creates_missing_directories(): void
    {
        $path = $this->basePath . '/deeply/nested/dir/messages.php';
        $this->assertDirectoryDoesNotExist(dirname($path));

        $this->service->save($path, ['a' => 'b']);

        $this->assertFileExists($path);
    }
}
