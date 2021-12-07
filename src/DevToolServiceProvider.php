<?php

namespace DevTool\LavavelDevTool;

use Illuminate\Support\ServiceProvider;
use DevTool\LavavelDevTool\Console\ModelAnnotationHelperCommand;

class DevToolServiceProvider extends ServiceProvider{
    
    public function boot(){
        if ($this->app->runningInConsole()) {
            $configPath = __DIR__ . '/../config/dev-tool.php';
            $this->publishes([
                $configPath => config_path('dev-tool.php'),
            ],'config');
        }
    }

    public function register()
    {
        $this->registerCommands();
    }

    protected function registerCommands(){
        $this->commands([
            ModelAnnotationHelperCommand::class
        ]);
    }
}