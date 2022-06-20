<?php
declare(strict_types=1);

namespace DevTool\LaravelDevTool\Console;

use DevTool\LaravelDevTool\Service\Generator;
use Illuminate\Console\Command;

class GenerateOpenApiCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'openApi:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'build openApi json';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info($this->description);
        Generator::generateDocs();
    }
}
