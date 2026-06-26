<?php

namespace AnourValar\LaravelInterpreter\Tests;

abstract class AbstractSuite extends \Orchestra\Testbench\TestCase
{
    /**
     * Temporary base directory for the test's filesystem fixtures.
     *
     * @var string
     */
    protected string $basePath;

    /**
     * Temporary lang directory (\App::langPath()).
     *
     * @var string
     */
    protected string $langPath;

    /**
     * Temporary views directory (config('view.paths')).
     *
     * @var string
     */
    protected string $viewPath;

    /**
     * Temporary vendor directory (base_path('vendor')).
     *
     * @var string
     */
    protected string $vendorPath;

    /**
     * Init
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/laravel_interpreter_' . uniqid('', true);
        $this->langPath = $this->basePath . '/lang';
        $this->viewPath = $this->basePath . '/views';
        $this->vendorPath = $this->basePath . '/vendor';

        $this->makeDir($this->langPath);
        $this->makeDir($this->viewPath);
        $this->makeDir($this->vendorPath);

        parent::setUp();
    }

    /**
     * Teardown
     *
     * @return void
     */
    protected function tearDown(): void
    {
        try {
            $this->deleteDir($this->basePath);
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Point the testbench application at our temporary fixtures.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->setBasePath($this->basePath);
        $app->useLangPath($this->langPath);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('view.paths', [$this->viewPath]);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \AnourValar\LaravelInterpreter\Providers\LaravelInterpreterServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [

        ];
    }

    /**
     * Write a lang file (php-array or json) into the temporary lang directory.
     *
     * @param string $relativePath
     * @param array|string $contents
     * @return string
     */
    protected function putLang(string $relativePath, array|string $contents): string
    {
        $path = $this->langPath . '/' . ltrim($relativePath, '/');
        $this->makeDir(dirname($path));

        if (is_array($contents)) {
            if (str_ends_with($path, '.json')) {
                $data = json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            } else {
                $data = "<?php\n\nreturn " . var_export($contents, true) . ";\n";
            }
        } else {
            $data = $contents;
        }

        file_put_contents($path, $data);

        return $path;
    }

    /**
     * Write a view file into the temporary views directory.
     *
     * @param string $relativePath
     * @param string $contents
     * @return string
     */
    protected function putView(string $relativePath, string $contents): string
    {
        $path = $this->viewPath . '/' . ltrim($relativePath, '/');
        $this->makeDir(dirname($path));

        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Write a view file into a fake vendor package (base_path('vendor')).
     *
     * @param string $relativePath
     * @param string $contents
     * @return string
     */
    protected function putVendorView(string $relativePath, string $contents): string
    {
        $path = $this->vendorPath . '/' . ltrim($relativePath, '/');
        $this->makeDir(dirname($path));

        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Generate a valid schema file for the given target locale.
     *
     * @param string $targetLocale
     * @param array $overrides
     * @return array
     */
    protected function putSchema(string $targetLocale, array $overrides = []): array
    {
        $json = file_get_contents(__DIR__ . '/../src/resources/schema.json');
        $json = str_replace('%LOCALE%', $targetLocale, $json);
        $json = str_replace('%DEFAULT_LOCALE%', 'en', $json);

        $schema = array_replace(json_decode($json, true), $overrides);

        file_put_contents(
            $this->langPath . '/' . $targetLocale . '_schema.json',
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $schema;
    }

    /**
     * Read the raw contents of a file inside the temporary lang directory.
     *
     * @param string $relativePath
     * @return string
     */
    protected function getLang(string $relativePath): string
    {
        return file_get_contents($this->langPath . '/' . ltrim($relativePath, '/'));
    }

    /**
     * @param string $path
     * @return void
     */
    protected function makeDir(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * @param string $path
     * @return void
     */
    protected function deleteDir(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        // The package's mkdir() may leave directories with broken permissions
        // (it passes a string mode), so restore access before traversing.
        @chmod($path, 0755);

        foreach (scandir($path) as $item) {
            if (in_array($item, ['.', '..'], true)) {
                continue;
            }

            $fullPath = $path . '/' . $item;

            if (is_dir($fullPath)) {
                $this->deleteDir($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }
}
