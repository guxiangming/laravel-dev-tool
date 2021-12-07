<?php
declare(strict_types=1);

namespace DevTool\LaravelDevTool\Console;

use Barryvdh\Reflection\DocBlock;
use Barryvdh\Reflection\DocBlock\Context;
use Barryvdh\Reflection\DocBlock\Serializer as DocBlockSerializer;
use Barryvdh\Reflection\DocBlock\Tag;
use Composer\Autoload\ClassMapGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionObject;
use phpDocumentor\Reflection\Types\ContextFactory;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;


class ModelAnnotationHelperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan model-annotation-helper:generate
     * php artisan model-annotation-helper:generate --model=""
     * php artisan model-annotation-helper:generate --dir=""
     * php artisan model-annotation-helper:generate --dir="" --model=""
     * php artisan model-annotation-helper:generate --ignored-config=true --model="" --dir="" 忽略config配置只生成指定位置
     *
     * @var string
     */
    protected $signature = 'model-annotation-helper:generate
    {--model=}
    {--ignored-config=}
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
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;

    }

    public function handle()
    {
        $this->initParams();
        $models = $this->getModels();
        if (empty($models)) {
            $this->error("请配置需要注解生成模型类或目录");
        }
        $this->info("已准备，即将处理的Model...");
        //生成注解
        $this->genAnnotation($models);
    }

    public function initParams()
    {
        $this->annotation = [];
        $this->properties = [];
        $this->methods = [];
        //是否默认覆盖
        $this->write = true;
        $this->reset = false;
        $this->phpstorm_noinspections = false;
        $this->dateColumnOfClass = class_exists(\Illuminate\Support\Facades\Date::class)
            ? '\\' . get_class(\Illuminate\Support\Facades\Date::now())
            : '\Illuminate\Support\Carbon';
    }

    /**
     * 生成model注解
     *
     * @param array $models
     */
    public function genAnnotation(array $models)
    {
        $output = "<?php
\n\n";
        $hasDoctrine = interface_exists('Doctrine\DBAL\Driver');
        foreach ($models as $class) {
            try {
                if (class_exists($class)) {
                    $reflectionClass = new \ReflectionClass($class);
                    if (!$reflectionClass->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
                        continue;
                    }
                    //过滤掉工具类
                    if (!$reflectionClass->IsInstantiable()) {
                        continue;
                    }
                    //注解字段
                    $model = $this->laravel->make($class);
                    $this->genAnnotionFromTableColumn($model);
                    //根据cast定义转换字段
                    if (method_exists($model, 'getCasts')) {
                        $this->transformPropertiesTypeFromCast($model);
                    }
                    //
                    $this->getPropertiesFromMethods($model);
                    $output .= $this->createPhpDocs($class);
                    $ignore[] = $class;
                    $this->nullableColumns = [];
                } else {
                    $this->warn("类不存在 {$class}");
                }
            } catch (\Throwable $t) {
                dump($t->getTraceAsString());
                $this->error(sprintf("异常错误：%s \n位置: %d \n", $t->getMessage(), $t->getLine()));
            }
        }
    }

    /**
     *
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function getPropertiesFromMethods($model)
    {
        $methods = get_class_methods($model);
        if ($methods) {
            sort($methods);
            foreach ($methods as $method) {
                if (in_array($method, [
                    'scopePaginateFilter', 'scopeSimplePaginateFilter', 'scopeWhereBeginsWith', 'scopeWhereEndsWith', 'scopeWhereLike'
                ])) {
                    continue;
                }
                if (
                    Str::startsWith($method, 'get') && Str::endsWith(
                        $method,
                        'Attribute'
                    ) && $method !== 'getAttribute'
                ) {
                    //Magic get<name>Attribute
                    $name = Str::snake(substr($method, 3, -9));
                    if (!empty($name)) {
                        $reflection = new \ReflectionMethod($model, $method);
                        $type = $this->getReturnType($reflection);
                        $type = $this->getTypeInModel($model, $type);
                        $this->setProperty($name, $type, true, null);
                    }
                } elseif (
                    Str::startsWith($method, 'set') && Str::endsWith(
                        $method,
                        'Attribute'
                    ) && $method !== 'setAttribute'
                ) {
                    //Magic set<name>Attribute
                    $name = Str::snake(substr($method, 3, -9));
                    if (!empty($name)) {
                        $this->setProperty($name, null, null, true);
                    }
                } elseif (Str::startsWith($method, 'scope') && $method !== 'scopeQuery') {
                    //Magic set<name>Attribute
                    $name = Str::camel(substr($method, 5));
                    if (!empty($name)) {
                        $reflection = new \ReflectionMethod($model, $method);
                        $args = $this->getParameters($reflection);
                        array_shift($args);
                        $builder = $this->getClassNameInDestinationFile(
                            $reflection->getDeclaringClass(),
                            \Illuminate\Database\Eloquent\Builder::class
                        );
                        $modelName = $this->getClassNameInDestinationFile(
                            $reflection->getDeclaringClass(),
                            $reflection->getDeclaringClass()->getName()
                        );
                        $this->setMethod($name, $builder . '|' . $modelName, $args);
                    }
                } elseif (in_array($method, ['query', 'newQuery', 'newModelQuery'])) {
                    $builder = $this->getClassNameInDestinationFile($model, get_class($model->newModelQuery()));

                    $this->setMethod(
                        $method,
                        $builder . "|" . $this->getClassNameInDestinationFile($model, get_class($model))
                    );
                } elseif (
                    !method_exists('Illuminate\Database\Eloquent\Model', $method)
                    && !Str::startsWith($method, 'get')
                ) {
                    $reflection = new \ReflectionMethod($model, $method);

                    if ($returnType = $reflection->getReturnType()) {
                        $type = $returnType instanceof \ReflectionNamedType
                            ? $returnType->getName()
                            : (string)$returnType;
                    } else {
                        $type = (string)$this->getReturnTypeFromDocBlock($reflection);
                    }

                    $file = new \SplFileObject($reflection->getFileName());
                    $file->seek($reflection->getStartLine() - 1);

                    $code = '';
                    while ($file->key() < $reflection->getEndLine()) {
                        $code .= $file->current();
                        $file->next();
                    }
                    $code = trim(preg_replace('/\s\s+/', '', $code));
                    $begin = intval(strpos($code, 'function('));
                    $code = substr($code, $begin, strrpos($code, '}') - $begin + 1);
                    foreach (
                        array(
                            'hasMany' => '\Illuminate\Database\Eloquent\Relations\HasMany',
                            'hasManyThrough' => '\Illuminate\Database\Eloquent\Relations\HasManyThrough',
                            'hasOneThrough' => '\Illuminate\Database\Eloquent\Relations\HasOneThrough',
                            'belongsToMany' => '\Illuminate\Database\Eloquent\Relations\BelongsToMany',
                            'hasOne' => '\Illuminate\Database\Eloquent\Relations\HasOne',
                            'belongsTo' => '\Illuminate\Database\Eloquent\Relations\BelongsTo',
                            'morphOne' => '\Illuminate\Database\Eloquent\Relations\MorphOne',
                            'morphTo' => '\Illuminate\Database\Eloquent\Relations\MorphTo',
                            'morphMany' => '\Illuminate\Database\Eloquent\Relations\MorphMany',
                            'morphToMany' => '\Illuminate\Database\Eloquent\Relations\MorphToMany',
                            'morphedByMany' => '\Illuminate\Database\Eloquent\Relations\MorphToMany'
                        ) as $relation => $impl
                    ) {
                        $search = '$this->' . $relation . '(';
                        if (stripos($code, $search) || ltrim($impl, '\\') === ltrim((string)$type, '\\')) {
                            //Resolve the relation's model to a Relation object.
                            $methodReflection = new \ReflectionMethod($model, $method);
                            if ($methodReflection->getNumberOfParameters()) {
                                continue;
                            }
                            $relationObj = Relation::noConstraints(function () use ($model, $method) {
                                return $model->$method();
                            });

                            if ($relationObj instanceof Relation) {
                                $relatedModel = $this->getClassNameInDestinationFile(
                                    $model,
                                    get_class($relationObj->getRelated())
                                );

                                $relations = [
                                    'hasManyThrough',
                                    'belongsToMany',
                                    'hasMany',
                                    'morphMany',
                                    'morphToMany',
                                    'morphedByMany',
                                ];
                                if (strpos(get_class($relationObj), 'Many') !== false) {
                                    //Collection or array of models (because Collection is Arrayable)
                                    $relatedClass = '\\' . get_class($relationObj->getRelated());
                                    $collectionClass = $this->getCollectionClass($relatedClass);
                                    $collectionClassNameInModel = $this->getClassNameInDestinationFile(
                                        $model,
                                        $collectionClass
                                    );
                                    $this->setProperty(
                                        $method,
                                        $collectionClassNameInModel . '|' . $relatedModel . '[]',
                                        true,
                                        null
                                    );
                                    //总数暂不统计
//                                    $this->setProperty(
//                                        Str::snake($method) . '_count',
//                                        'int|null',
//                                        true,
//                                        false
//                                    );
                                } elseif ($relation === "morphTo") {
                                    // Model isn't specified because relation is polymorphic
                                    $this->setProperty(
                                        $method,
                                        $this->getClassNameInDestinationFile($model, Model::class) . '|\Eloquent',
                                        true,
                                        null
                                    );
                                } else {
                                    //Single model is returned
                                    $this->setProperty(
                                        $method,
                                        $relatedModel,
                                        true,
                                        null,
                                        '',
                                        $this->isRelationNullable($relation, $relationObj)
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 转换字段根据定义的类型
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     */
    protected function transformPropertiesTypeFromCast($model)
    {
        $casts = $model->getCasts();
        foreach ($casts as $name => $type) {
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $realType = 'boolean';
                    break;
                case 'string':
                    $realType = 'string';
                    break;
                case 'array':
                case 'json':
                    $realType = 'array';
                    break;
                case 'object':
                    $realType = 'object';
                    break;
                case 'int':
                case 'integer':
                case 'timestamp':
                    $realType = 'integer';
                    break;
                case 'real':
                case 'double':
                case 'float':
                    $realType = 'float';
                    break;
                case 'date':
                case 'datetime':
                    $realType = $this->dateClass;
                    break;
                case 'collection':
                    $realType = '\Illuminate\Support\Collection';
                    break;
                default:
                    $realType = class_exists($type) ? ('\\' . $type) : 'mixed';
                    break;
            }

            if (!isset($this->properties[$name])) {
                continue;
            } else {
                $realType = $this->checkForCustomLaravelCasts($realType);
                $this->properties[$name]['type'] = $this->getTypeInModel($model, $realType);

                if (isset($this->nullableColumns[$name])) {
                    $this->properties[$name]['type'] .= '|null';
                }
            }
        }
    }


    /**
     * @param string $type
     * @return string|null
     * @throws \ReflectionException
     */
    protected function checkForCustomLaravelCasts(string $type): ?string
    {
        if (!class_exists($type) || !interface_exists(CastsAttributes::class)) {
            return $type;
        }

        $reflection = new \ReflectionClass($type);

        if (!$reflection->implementsInterface(CastsAttributes::class)) {
            return $type;
        }

        $methodReflection = new \ReflectionMethod($type, 'get');

        $type = $this->getReturnTypeFromReflection($methodReflection);

        if ($type === null) {
            $type = $this->getReturnTypeFromDocBlock($methodReflection);
        }

        return $type;
    }

    /**
     * 生成注解文件
     * 
     * @param string $class
     * @return string
     */
    protected function createPhpDocs($class)
    {

        $reflection = new ReflectionClass($class);
        $namespace = $reflection->getNamespaceName();
        $classname = $reflection->getShortName();
        $originalDoc = $reflection->getDocComment();
        $keyword = $this->getClassKeyword($reflection);
        $interfaceNames = array_diff_key(
            $reflection->getInterfaceNames(),
            $reflection->getParentClass()->getInterfaceNames()
        );

        if ($this->reset) {
            $phpdoc = new DocBlock('', new Context($namespace));
            if ($this->keep_text) {
                $phpdoc->setText(
                    (new DocBlock($reflection, new Context($namespace)))->getText()
                );
            }
        } else {
            $phpdoc = new DocBlock($reflection, new Context($namespace));
        }

        if (!$phpdoc->getText()) {
            $phpdoc->setText($class);
        }

        $properties = array();
        $methods = array();
        foreach ($phpdoc->getTags() as $tag) {
            $name = $tag->getName();
            if ($name == "property" || $name == "property-read" || $name == "property-write") {
                $properties[] = $tag->getVariableName();
            } elseif ($name == "method") {
                $methods[] = $tag->getMethodName();
            }
        }

        foreach ($this->properties as $name => $property) {
            $name = "\$$name";

            if (in_array($name, $properties)) {
                continue;
            }
            if ($property['read'] && $property['write']) {
                $attr = 'property';
            } elseif ($property['write']) {
                $attr = 'property-write';
            } else {
                $attr = 'property-read';
            }

            $tagLine = trim("@{$attr} {$property['type']} {$name} {$property['comment']}");
            $tag = Tag::createInstance($tagLine, $phpdoc);
            $phpdoc->appendTag($tag);
        }

        ksort($this->methods);

        foreach ($this->methods as $name => $method) {
            if (in_array($name, $methods)) {
                continue;
            }
            $arguments = implode(', ', $method['arguments']);
            $tag = Tag::createInstance("@method static {$method['type']} {$name}({$arguments})", $phpdoc);
            $phpdoc->appendTag($tag);
        }
        //结束标记
//        if ($this->write && ! $phpdoc->getTagsByName('mixin')) {
//            $eloquentClassNameInModel = $this->getClassNameInDestinationFile($reflection, 'Eloquent');
//            $phpdoc->appendTag(Tag::createInstance("@mixin " . $eloquentClassNameInModel, $phpdoc));
//        }
        if ($this->phpstorm_noinspections) {
            $phpdoc->appendTag(Tag::createInstance("@noinspection PhpFullyQualifiedNameUsageInspection", $phpdoc));
            $phpdoc->appendTag(
                Tag::createInstance("@noinspection PhpUnnecessaryFullyQualifiedNameInspection", $phpdoc)
            );
        }

        $serializer = new DocBlockSerializer();
        $serializer->getDocComment($phpdoc);
        $docComment = $serializer->getDocComment($phpdoc);


        if ($this->write) {
            $filename = $reflection->getFileName();
            $contents = $this->files->get($filename);
            if ($originalDoc) {
                $contents = str_replace($originalDoc, $docComment, $contents);
            } else {
                $replace = "{$docComment}\n";
                $pos = strpos($contents, "final class {$classname}") ?: strpos($contents, "class {$classname}");
                if ($pos !== false) {
                    $contents = substr_replace($contents, $replace, $pos, 0);
                }
            }
            if ($this->files->put($filename, $contents)) {
                $this->info('注解覆盖生成成功' . $filename);
            }
        }

        $output = "namespace {$namespace}{\n{$docComment}\n\t{$keyword}class {$classname} extends \Eloquent ";

        if ($interfaceNames) {
            $interfaces = implode(', \\', $interfaceNames);
            $output .= "implements \\{$interfaces} ";
        }

        return $output . "{}\n}\n\n";
    }

    /**
     * @param ReflectionClass $reflection
     * @return string
     */
    protected function getClassKeyword(ReflectionClass $reflection)
    {
        if ($reflection->isFinal()) {
            $keyword = 'final ';
        } elseif ($reflection->isAbstract()) {
            $keyword = 'abstract ';
        } else {
            $keyword = '';
        }

        return $keyword;
    }

    /**
     * 反射获取返回类型
     *
     * @param \ReflectionMethod $reflection
     *
     * @return null|string
     */
    protected function getReturnTypeFromDocBlock(\ReflectionMethod $reflection)
    {
        $phpDocContext = (new ContextFactory())->createFromReflector($reflection);
        $context = new Context(
            $phpDocContext->getNamespace(),
            $phpDocContext->getNamespaceAliases()
        );
        $type = null;
        $phpdoc = new DocBlock($reflection, $context);

        if ($phpdoc->hasTag('return')) {
            $type = $phpdoc->getTagsByName('return')[0]->getType();
        }

        return $type;
    }

    public function genAnnotionFromTableColumn(\Illuminate\Database\Eloquent\Model $model)
    {
        $resolve = $this->resolveColumnType($model);
    }


    protected function resolveColumnType($model)
    {
        $tableName = $model->getConnection()->getTablePrefix() . $model->getTable();
        $schema = $model->getConnection()->getDoctrineSchemaManager($tableName);
        $columns = $schema->listTableColumns($tableName);
        $columnMaps = [];
        if (!empty($columns)) {
            foreach ($columns as $column) {
                $name = $column->getName();
                if (in_array($name, $model->getDates())) {
                    $type = $this->dateColumnOfClass;
                } else {
                    $type = $column->getType()->getName();
                    switch ($type) {
                        case 'string':
                        case 'text':
                        case 'date':
                        case 'time':
                        case 'guid':
                        case 'datetime':
                        case 'decimal':
                            $type = 'string';
                            break;
                        case 'integer':
                        case 'bigint':
                        case 'smallint':
                            $type = 'integer';
                            break;
                        case 'boolean':
                            $type = 'boolean';
                            break;
                        case 'float':
                            $type = 'float';
                            break;
                        default:
                            $type = 'mixed';
                            break;
                    }
                }
                $comment = $column->getComment();
                //字段是否允许为空
                $isNullable = $column->getNotnull();
                //绑定字段属性
                $this->setProperty(
                    $name,
                    $this->getTypeInModel($model, $type),
                    true,
                    true,
                    $comment,
                    !$column->getNotnull()
                );
//                //引入方法解析
//                $this->setMethod(
//                    Str::camel("where_" . $name),
//                    $this->getClassNameInDestinationFile($model, \Illuminate\Database\Eloquent\Builder::class)
//                    . '|'
//                    . $this->getClassNameInDestinationFile($model, get_class($model)),
//                    array('$value')
//                );
            }
        }
    }

    protected function setMethod($name, $type = '', $arguments = array())
    {
        $methods = array_change_key_case($this->methods, CASE_LOWER);

        if (!isset($methods[strtolower($name)])) {
            $this->methods[$name] = array();
            $this->methods[$name]['type'] = $type;
            $this->methods[$name]['arguments'] = $arguments;
        }
    }

    /**
     * 设置属性
     * 
     * @param string $name
     * @param string|null $type
     * @param bool|null $read
     * @param bool|null $write
     * @param string|null $comment
     * @param bool $nullable
     */
    protected function setProperty($name, $type = null, $read = null, $write = null, $comment = '', $nullable = false)
    {
        if (!isset($this->properties[$name])) {
            $this->properties[$name] = array();
            $this->properties[$name]['type'] = 'mixed';
            $this->properties[$name]['read'] = false;
            $this->properties[$name]['write'] = false;
            $this->properties[$name]['comment'] = (string)$comment;
        }
        if ($type !== null) {
            $newType = $type;
            if ($nullable) {
                $newType .= '|null';
            }
            $this->properties[$name]['type'] = $newType;
        }
        if ($read !== null) {
            $this->properties[$name]['read'] = $read;
        }
        if ($write !== null) {
            $this->properties[$name]['write'] = $write;
        }
    }


    /**
     * 获取model文件
     *
     * @return array
     */
    protected function getModels()
    {
        $ignoredConfig = $this->option('ignored-config');
        if (empty($ignoredConfig)) {
            $config = $this->laravel['config']->get('dev-tool');
        }
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

    /**
     * 获取类命名空间情况
     * 
     * @param object|ReflectionClass $model
     * @param string $className
     * @return string
     */
    protected function getClassNameInDestinationFile(object $model, string $className): string
    {
        $reflection = $model instanceof ReflectionClass
            ? $model
            : new ReflectionObject($model);

        $className = trim($className, '\\');
        $writingToExternalFile = !$this->write;
        $classIsNotInExternalFile = $reflection->getName() !== $className;

        if ($writingToExternalFile && $classIsNotInExternalFile) {
            return '\\' . $className;
        }

        $usedClassNames = $this->getUsedClassNames($reflection);
        if (!empty($usedClassNames[$className])) {
            return $usedClassNames[$className];
        }

        $class = new \ReflectionClass($className);
        //兼容当前命名空间情况
        if ($reflection->getNamespaceName() == $class->getNamespaceName()) {
            return str_replace($reflection->getNamespaceName() . '\\', '', $className);
        } else {
            return '\\' . $className;
        }
    }

    /**
     * @param ReflectionClass $reflection
     * @return string[]
     */
    protected function getUsedClassNames(ReflectionClass $reflection): array
    {
        $namespaceAliases = array_flip((new ContextFactory())->createFromReflector($reflection)->getNamespaceAliases());
        $namespaceAliases[$reflection->getName()] = $reflection->getShortName();

        return $namespaceAliases;
    }

    /**
     * @param object|ReflectionClass $model
     * @param string $type
     * @return string
     */
    protected function getTypeInModel(object $model, ?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if (class_exists($type)) {
            $type = $this->getClassNameInDestinationFile($model, $type);
        }

        return $type;
    }

    /**
     * @param string $className
     * @return string
     */
    protected function getCollectionClass($className)
    {
        if (!method_exists($className, 'newCollection')) {
            return '\Illuminate\Database\Eloquent\Collection';
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $className();
        return '\\' . get_class($model->newCollection());
    }

    /**
     * 检测关联模型是否允许空
     *
     * @param string $relation
     * @param Relation $relationObj
     *
     * @return bool
     */
    protected function isRelationNullable(string $relation, Relation $relationObj): bool
    {
        $reflectionObj = new ReflectionObject($relationObj);

        if (in_array($relation, ['hasOne', 'hasOneThrough', 'morphOne'], true)) {
            $defaultProp = $reflectionObj->getProperty('withDefault');
            $defaultProp->setAccessible(true);

            return !$defaultProp->getValue($relationObj);
        }

        if (!$reflectionObj->hasProperty('foreignKey')) {
            return false;
        }

        $fkProp = $reflectionObj->getProperty('foreignKey');
        $fkProp->setAccessible(true);

        return isset($this->nullableColumns[$fkProp->getValue($relationObj)]);
    }

    protected function getReturnType(\ReflectionMethod $reflection): ?string
    {
        $type = $this->getReturnTypeFromDocBlock($reflection);
        if ($type) {
            return $type;
        }

        return $this->getReturnTypeFromReflection($reflection);
    }

    protected function getReturnTypeFromReflection(\ReflectionMethod $reflection): ?string
    {
        $returnType = $reflection->getReturnType();
        if (!$returnType) {
            return null;
        }

        $type = $returnType instanceof \ReflectionNamedType
            ? $returnType->getName()
            : (string)$returnType;

        if (!$returnType->isBuiltin()) {
            $type = '\\' . $type;
        }

        if ($returnType->allowsNull()) {
            $type .= '|null';
        }

        return $type;
    }

    /**
     * Get the parameters and format them correctly
     *
     * @param $method
     * @return array
     */
    public function getParameters($method)
    {
        $params = array();
        $paramsWithDefault = array();
        /** @var \ReflectionParameter $param */
        foreach ($method->getParameters() as $param) {
            $paramClass = $param->getClass();
            $paramStr = (!is_null($paramClass) ? '\\' . $paramClass->getName() . ' ' : '') . '$' . $param->getName();
            $params[] = $paramStr;
            if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                if (is_bool($default)) {
                    $default = $default ? 'true' : 'false';
                } elseif (is_array($default)) {
                    $default = '[]';
                } elseif (is_null($default)) {
                    $default = 'null';
                } elseif (is_int($default)) {
                    //$default = $default;
                } else {
                    $default = "'" . trim($default) . "'";
                }
                $paramStr .= " = $default";
            }
            $paramsWithDefault[] = $paramStr;
        }
        return $paramsWithDefault;
    }
}
