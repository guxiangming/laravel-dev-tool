<?php

namespace SheinPlm\LavavelDevTool;

use Illuminate\Support\ServiceProvider;
use SheinPlm\LavavelDevTool\Console\ModelAnnotationHelperCommand;
use SheinPlm\LavavelDevTool\Console\ModelIdeHelperCommand;

class DevToolServiceProvider extends ServiceProvider{


    public function boot(){

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