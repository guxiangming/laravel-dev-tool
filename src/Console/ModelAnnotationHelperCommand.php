<?php
declare(strict_types=1);

namespace Barryvdh\LaravelIdeHelper\Console;

use Composer\Autoload\ClassMapGenerator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ModelAnnotationHelperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model-annotation-helper:generate
    {--model=}
    {--dir=}';

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

    public function handle()
    {
        $config = $this->laravel['config']->get('dev-tool');
        $model = $this->option('model');
        $dir = $this->option('dir');
        $models = [];
        if ($model && !class_exists($model)) {
            $this->error("模型类 不存在");
            exit(0);
        }
        if ($dir && !is_dir($dir)) {
            $this->error("目录 不存在");
            exit(1);
        }
        $models = $this->getModels($config, $model, $dir);
        dd($models);
        if (empty($models)) {
            $this->error("请配置需要注解生成模型类或目录");
            exit(2);
        }
    }

    protected function getModels($config, $model, $dir)
    {
        $ignoredModels = [];
        $autoModels = [];
        //获取需要清除的类
        if (!empty($config['model_annotation_helper']['ignored_dir'])) {
            foreach ($config['model_annotation_helper']['auto_dir'] as $value) {
                $ignoredModels = array_merge($ignoredModels, $this->loadModelClassFromDir($value));
            }
        }
        //获取需要清除的model
        if (!empty($config['model_annotation_helper']['ignored_model'])) {
            $ignoredModels = array_merge($ignoredModels, $config['model_annotation_helper']['ignored_model']);
        }
        $ignoredModels = array_unique($ignoredModels);
        //获取需要加载的类
        if (!empty($config['model_annotation_helper']['auto_dir'])) {
            foreach ($config['model_annotation_helper']['auto_dir'] as $value) {
                $autoModels = array_merge($ignoredModels, $this->loadModelClassFromDir($value));
            }
        }
        //获取需要清除的model
        if (!empty($config['model_annotation_helper']['auto_model'])) {
            $autoModels = array_merge($ignoredModels, $config['model_annotation_helper']['auto_model']);
        }
        if (!empty($model)) {
            array_push($autoModels, $model);
        }
        if (!empty($dir)) {
            $autoModels = array_merge($ignoredModels, $this->loadModelClassFromDir($dir));
            array_push($autoModels, $model);
        }
        $autoModels = array_unique($autoModels);
        //返回需要注释的类
        return array_filter(array_diff($autoModels, $ignoredModels));
    }

    protected function loadModelClassFromDir(string $dir)
    {
        $models = [];
        $dir = base_path() . '/' . $dir;
        $dirs = glob($dir, GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            if (file_exists($dir)) {
                //获取目录下的模型
                $classMap = ClassMapGenerator::createMap($dir);
                $models = array_merge($models, array_keys($classMap));
            }
        }
        return $models;
    }

}
