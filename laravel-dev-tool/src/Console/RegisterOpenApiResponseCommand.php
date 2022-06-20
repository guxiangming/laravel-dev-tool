<?php
declare(strict_types=1);

namespace DevTool\LaravelDevTool\Console;

use DevTool\LaravelDevTool\Service\Generator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

class RegisterOpenApiResponseCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'openApi:register';

    protected $signature = 'openApi:register
    {route : 请求方式+路由如 post:/suppliers}
    {name : 接口名称}
    ';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'build openApi json mapp class';

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info($this->description);
        $route = $this->argument('route');
        $name = $this->argument('name');
        $stub = $this->files->get($this->getGeneratorStub());
        $stub = str_replace(
            ['DummyName'],
            [$name],
            $stub
        );
        $generator = new Generator();
        [$mapDir, $responseDir] = $generator->initMapDir();
        $responseFileName = $this->formattingRoute($route);
        $responseFile = $responseDir . '/' . $responseFileName;
        if (!$this->files->exists($responseFile)) {
            $this->files->put($responseFile, $stub);
        }

        $routeArr = explode(':', $route);
        $routeArr[0] = strtolower($routeArr[0]);
        $key = implode(':', $routeArr);
        $value = <<<EOF
'{$key}' => \$dir . '{$responseFileName}',
EOF;
        $mapStub = $this->files->get($this->getMapStub());
        //register map
        $mapFile = $generator->getMapFile();
        if ($this->files->exists($mapFile)) {
            $content = require $mapFile;
            if (isset($content[$key])) {
                $this->error('has ' . $key);
            }
            $fileContent = $this->files->get($mapFile);
            $fileContent = str_replace(
                ['];'],
                ["\t" . $value . PHP_EOL . '];'],
                $fileContent
            );
            $this->files->put($mapFile, $fileContent);
        } else {
            $mapStub = str_replace(
                ['DummyContent'],
                [$value . PHP_EOL],
                $mapStub
            );
            $this->files->put($mapFile, $mapStub);
        }
        $this->info('end');
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getGeneratorStub(): string
    {
        return dirname(__FILE__, 3) . '/stubs/openApiGenerator.stub';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getMapStub(): string
    {
        return dirname(__FILE__, 3) . '/stubs/openApiMap.stub';
    }

    /**
     * formatting
     */
    public function formattingRoute(string $route): string
    {
        $mapFileName = str_replace([':', '/'], ['_', '_'], $route);
        $mapFileNameArr = explode('_', $mapFileName);
        $mapFileNameArr[0] = strtolower($mapFileNameArr[0]);

        return implode('_', $mapFileNameArr) . '.php';
    }
}
