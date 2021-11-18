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
        $this->initParams();
        $models = $this->getModels();
        if (empty($models)) {
            $this->error("请配置需要注解生成模型类或目录");
        }
        $this->info("已准备，即将处理的Model...");
        $annotation=$this->genAnnotation($models);
        dump($models);
    }

    public function initParams(){
        $this->annotation = [];
    }

    /**
     * 生成model注解
     *
     * @param array $models
     */
    public function genAnnotation(array $models){
        foreach ($models as $class){
            try{
                if (class_exists($class)) {
                    $reflectionClass = new \ReflectionClass($class);
                    if (!$reflectionClass->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
                        continue;
                    }
                    //过滤掉工具类
                    if (!$reflectionClass->IsInstantiable()) {
                        continue;
                    }
                    $model = $this->laravel->make($class);
                    $this->genAnnotionFromTableColumn($model);
                }else{
                    $this->warn("类不存在 {$class}");
                }
            }catch (\Throwable $t){
                $this->error(sprintf("异常错误：%s \n位置: %d \n", $t->getMessage(), $t->getLine()));
            }
        }
    }

    public function genAnnotionFromTableColumn(\Illuminate\Database\Eloquent\Model $model){
        $tableName = $model->getConnection()->getTablePrefix() . $model->getTable();
        $schema = $model->getConnection()->getDoctrineSchemaManager($tableName);
        $columns = $schema->listTableColumns($tableName);
        if(!empty($columns)){
            dd($columns);
            foreach ($columns as $column){

                $name = $column->getName();
                $type = $this->resolveColumnType($column);
            }
        }
        dd($databasePlatform);
    }


    public function resolveColumnType($column){

    }

    /**
     * 获取model文件
     *
     * @return array
     */
    protected function getModels()
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
        $ignoredModels = [];
        $autoModels = [];
        //获取需要清除的类
        if (!empty($config['model_annotation_helper']['ignored_dir'])) {
            foreach ($config['model_annotation_helper']['ignored_dir'] as $value) {
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
                $autoModels = array_merge($autoModels, $this->loadModelClassFromDir($value));
            }
        }
        //获取需要加载的model
        if (!empty($config['model_annotation_helper']['auto_model'])) {
            $autoModels = array_merge($autoModels, $config['model_annotation_helper']['auto_model']);
        }
        if (!empty($model)) {
            array_push($autoModels, $model);
        }
        if (!empty($dir)) {
            $autoModels = array_merge($autoModels, $this->loadModelClassFromDir($dir));
            array_push($autoModels, $model);
        }
        $autoModels = array_unique($autoModels);
        //返回需要注释的类
        return array_filter(array_diff($autoModels, $ignoredModels));
    }

    /**
     * 加载目录的model
     *
     * @param string $dir
     * @return array|int[]|string[]
     */
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
