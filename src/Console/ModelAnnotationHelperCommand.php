<?php

namespace SheinPlm\LavavelDevTool\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class ModelAnnotationHelperCommand extends Command
{
     /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model-annotation-helper:generate
                            {--dir= : }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '该工具目的是给model生成与补充ide的注解';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(){
        $modelClass=$this->argument('ModelClass');
        $dir=$this->option('dir');
        $models=[];
        if($modelClass&&class_exists($modelClass)){
            $this->error("model 不能存在");
        }

        if($dir&&file_exists($dir)){
            $this->error("model 不能存在");
        }

        if(empty($models)){
            $this->error("请指定需要注解生成model类或目录");
        }

        

    }

    protected function getDirModelClass(string $dir){
        foreach ($this->dirs as $dir) {
            $dir = base_path() . '/' . $dir;
            $dirs = glob($dir, GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                if (file_exists($dir)) {
                    $classMap = ClassMapGenerator::createMap($dir);
                    ksort($classMap);
                    foreach ($classMap as $model => $path) {
                        $models[] = $model;
                    }
                }
            }
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
          array('ModelClass', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Which models to include', array()),
        );
    }
}