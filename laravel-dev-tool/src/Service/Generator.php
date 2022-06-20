<?php

declare(strict_types=1);

namespace DevTool\LaravelDevTool\Service;

use Exception;
use ReflectionMethod;
use Illuminate\Routing\Route;
use ReflectionFunctionAbstract;
use Illuminate\Config\Repository;
use Illuminate\Support\Reflector;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use DevTool\LaravelDevTool\Template\OpenApi;
use Illuminate\Contracts\Foundation\Application;
use DevTool\LaravelDevTool\Tools\ResolveGenerator;

use Illuminate\Contracts\Container\BindingResolutionException;

class Generator
{
    use ResolveGenerator;

    /**
     * @var string
     */
    protected $docDir;

    /**
     * @var string
     */
    protected $docsFile;

    /**
     * @var array
     */
    protected $mapFile;

    /**
     * @var string
     */
    protected $requestClassName;

    /**
     * @var array
     */
    protected $excludedDirs;

    /**
     * @var array
     */
    protected $constants;

    /**
     * The container instance used by the route.
     *
     * @var Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $openApi;

    /**
     * @var Repository|Application|mixed
     */

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $msg = [];

    public function __construct()
    {
        $this->setDisplayErrors();
        $this->config = config('dev-tool.open_api') ?: [];
        $this->constants = config('dev-tool.open_api.constants') ?: [];
        $this->docDir = config('dev-tool.open_api.path.base_path');
        $this->docsFile = $this->docDir . '/' . config('dev-tool.open_api.path.file_name', 'openApi.json');
        $this->requestClassName = config('dev-tool.open_api.request_class_name');
        $this->container = $this->container ?: Container::getInstance();
    }

    /**
     * open errors
     */
    protected function setDisplayErrors(): void
    {
        ini_set('display_errors', 'on');
        error_reporting(E_ALL);
//        register_shutdown_function(function (){
//        });
    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public static function generateDocs()
    {
        (new static())->prepareDirectory()
            ->defineConstants()
            ->initOpenApi()
            ->scanRouters()
            ->saveJson();
    }

    /**
     * @return string
     */
    public function getFilePathName(): string
    {
        return $this->docsFile;
    }

    /**
     * @return string
     */
    public function getEncryptSecretKey(): string
    {
        $secretKey = $this->config['routes']['secret_key'];

        if (empty($secretKey)) {
            throw new Exception('secret_key is empty');
        }

        return encrypt($secretKey);
    }

    /**
     * @return string
     */
    public function getUloadUrl(): string
    {
        return $this->config['host'] . '/' . ltrim($this->config['routes']['upload'], '/');
    }

    /**
     * @return array
     */
    public function initMapDir(): array
    {
        $dir = dirname($this->config['request_response_map_file']);

        if (!File::exists($dir)) {
            File::makeDirectory($dir);
        }

        $responseDir = $dir . '/Response';

        if (!File::exists($responseDir)) {
            File::makeDirectory($responseDir);
        }

        return [$dir, $responseDir];
    }

    /**
     * @param array $params
     */
    public function upload(array $params): void
    {
        $secretKey = $this->config['routes']['secret_key'];

        if (empty($secretKey)) {
            throw new Exception('secret_key is empty');
        }

        if (empty($params['secret_key']) || decrypt($params['secret_key']) != $secretKey) {
            throw new Exception('secret_key is illegal');
        }

        if (empty($params['open_api'])) {
            throw new Exception('open_api is empty');
        }

        $this->openApi = json_decode($params['open_api'], true);
        if (!$this->openApi) {
            throw new Exception('open_api is errors');
        }
        $this->saveJson();
    }

    /**
     * Check directory structure and permissions.
     *
     * @return Generator
     * @throws Exception
     */
    protected function prepareDirectory(): Generator
    {
        if (File::exists($this->docDir) && !is_writable($this->docDir)) {
            throw new Exception('Documentation storage directory is not writable');
        }

        // delete all existing documentation
        if (File::exists($this->docDir)) {
            File::deleteDirectory($this->docDir);
        }

        File::makeDirectory($this->docDir);

        return $this;
    }

    /**
     * Define constant which will be replaced.
     *
     * @return Generator
     */
    protected function defineConstants(): Generator
    {
        if (!empty($this->constants)) {
            foreach ($this->constants as $key => $value) {
                defined($key) || define($key, $value);
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function saveJson(): Generator
    {
        file_put_contents(
            $this->docsFile,
            json_encode($this->openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $this;
    }

    /**
     *  init
     * @throws Exception
     */
    public function initOpenApi(): Generator
    {
        $this->openApi = [
            'swagger' => $this->config['swagger_version'],
            'info' => [
                'description' => $this->config['description'],
                'version' => $this->config['info_version'],
                'title' => $this->config['title'],
                'contact' => [
                    'name' => $this->config['operator'],
                ],
                'license' => [
                    'name' => $this->config['license'],
                ],
                'x-repoUrl' => $this->config['repository_url'],
            ],
            'host' => $this->config['host'],
            'basePath' => '/',
            'tags' => [
            ],
            'paths' => [

            ],
        ];

        return $this->setMapFile();
    }

    /**
     * @return string
     */
    public function getMapFile(): string
    {
        return config('dev-tool.open_api.request_response_map_file');
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function setMapFile(): Generator
    {
        $path = config('dev-tool.open_api.request_response_map_file');
        if (!File::exists($path)) {
            throw new Exception('not found request_response_map_file');
        }
        $this->mapFile = require $path;

        return $this;
    }

    /**
     * @throws BindingResolutionException
     * @throws Exception
     */
    public function scanRouters(): Generator
    {
        $routes = $this->container->make('router')->getRoutes()->getRoutes();
        /** @var Route $route */
        foreach ($routes as $route) {
            $uses = $route->getAction()['uses'];

            if (!is_string($uses)) {
                $this->buildMsg('scanRouters:' . $route->uri . ' nonsupport');

                continue;
            }
            list($controller, $method) = $this->parseControllerCallback($uses);
            $controller = ltrim($controller, '\\');

            if (!class_exists($controller)) {
                $this->buildMsg('scanRouters:throwable:message' . $controller . ' not found');

                continue;
            }

            try {
                $instance = $this->container->make($controller);
            } catch (\Throwable $t) {
                $this->buildMsg('scanRouters:throwable:message' . $t->getMessage());

                continue;
            }

            if (!method_exists($instance, $method)) {
                $this->buildMsg($method . ' not found');

                continue;
            }
            $request = $this->resolveMethodDependencies(new ReflectionMethod($instance, $method));

            if ($request) {
                $this->generatorOpenApi($route, $request, $method);
            }
        }

        return $this;
    }

    /**
     * Resolve the given method's type-hinted dependencies.
     *
     * @param ReflectionFunctionAbstract $reflector
     * @return Repository|Application|void
     */
    public function resolveMethodDependencies(\ReflectionFunctionAbstract $reflector)
    {
        foreach ($reflector->getParameters() as $parameter) {
            if (!Reflector::isParameterSubclassOf($parameter, $this->requestClassName)) {
                continue;
            }

            $className = Reflector::getParameterClassName($parameter);

            if (!$className || !class_exists($className)) {
                continue;
            }

            $requestClass = new $className();

            if (!$requestClass instanceof $this->requestClassName) {
                continue;
            }

            return $requestClass;
        }

        return;
    }

    /**
     * Parse the controller.
     *
     * @param $uses
     * @return array
     * @throws Exception
     */
    protected function parseControllerCallback($uses): array
    {
        if (false === mb_strpos($uses, '@')) {
            throw new Exception('controller failed');
        }

        return explode('@', $uses, 2);
    }

    /**
     * @param Route $route
     * @param $request
     * @param $controllerMethod
     */
    protected function generatorOpenApi(Route $route, $request, $controllerMethod)
    {
        $uri = '/' . ltrim($route->uri, '/');
        $method = $route->methods[0]; //any 请求默认标记为get
        $key = strtolower($method) . ':' . $uri;
        if (!isset($this->mapFile[$key]) || !File::exists($this->mapFile[$key])) {
            $this->buildMsg($key . ' no register map class');

            return;
        }

        $response = require $this->mapFile[$key];
        $node = &$this->openApi['paths'][$uri][strtolower($method)];
        $this->handleParameters($node, $response);

        if (property_exists($request, 'scene')) {
            $sceneRule = ($request->scene)[$controllerMethod];
        } else {
            $sceneRule = [];
        }
        $this->handleRequest($node, $request, $method, $response, $sceneRule);
        $this->handleResponse($node, $response);

        // api base info
        foreach ($response['openApi'] as $key => $value) {
            $node[$key] = $value;
        }

        unset($response, $response);
    }

    /**
     * @param $node
     * @param $response
     */
    protected function handleParameters(&$node, $response): void
    {
        $parameters = $response['headerParams'];

        foreach ($parameters as $key => $item) {
            $content = OpenApi::HEADER_PARAMETERS;
            $content['name'] = $key;
            $content['description'] = $item['description'] ?? '';
            $content['required'] = $item['required'] ?? false;
            $content['type'] = $item['type'] ?? 'string';
            $content['default'] = $item['default'] ?? 'application/json';
            $node['parameters'][] = $content;
        }
    }

    /**
     * @param $node
     * @param $request
     * @param $method
     * @param $response
     * @param array $sceneRule
     */
    protected function handleRequest(&$node, $request, $method, $response, array $sceneRule = []): void
    {
        $parameters = $request->rules();

        if (!empty($sceneRule)) {
            foreach ($parameters as $key => $item) {
                if (!in_array($sceneRule, $key)) {
                    unset($parameters[$key]);
                }
            }
        }
        $parametersAttributes = $request->attributes();
        $parametersDescribe = $response['requestDescribe'];
        $parametersDefaultValue = $response['requestDefaultValue'];
        $body = OpenApi::BODY_PARAMETERS;
        switch (strtolower($method)) {
            case 'get':
            case 'post':
                $properties = &$body['schema']['properties'];
                $required = &$body['schema']['required'];
                list($properties, $required) = $this->resolveBodyRules($parameters, $parametersAttributes, $parametersDescribe, $parametersDefaultValue);
                $body['schema'] = [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ];

                break;
        }
        $node['parameters'][] = $body;
    }

    /**
     * @param $node
     * @param $response
     */
    protected function handleResponse(&$node, $response): void
    {
        $parameters = $response['response'];
        $node['responses'] = OpenApi::RESPONSE;
        $schema = [];
        if (isset($parameters['is_array']) && $parameters['is_array']) {
            $schema['type'] = 'array';
            $schema['items'] = [
                'type' => $parameters['type'],
            ];
            $schema['items']['properties'] = [];
            $properties = &$schema['items']['properties'];
        } else {
            $schema['type'] = $parameters['type'];
            $schema['properties'] = [];
            $properties = &$schema['properties'];
        }

        if (!empty($parameters['_properties'])) {
            $this->recursiveProperties($parameters['_properties'], $properties);
        }

        $node['responses'][200]['schema'] = $schema;
    }

    /**
     * @param string $str
     */
    protected function buildMsg(string $str): void
    {
        $this->msg[] = $str;
        Log::info('Generator: ' . $str);
    }
}
