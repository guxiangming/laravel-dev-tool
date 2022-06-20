<?php

return [
    /**
     *  Model注解批量管理类
     */
    'model_annotation_helper' => [
        'auto_model' => [
            //自动注解的model
        ],
        'auto_dir' => [
            //自动注解的目录
        ],
        'ignored_model' => [
            //需要排除自动注解的model
            "App\Models\BaseModel",
        ],
        'ignored_dir' => [
            //需要排除自动注解的model
        ],
    ],

    /**
     *  OpenApi生成工具列表
     */
    'open_api' => [
        'swagger_version' => '2.0',
        'info_version' => '2.6.4.1-SNAPSHOT',
        'host' => '',//必填
        'description' => 'openApi',
        'title' => env('APP_NAME'),
        'operator' => '',
        'license' => '',
        'repository_url' => 'gitlab',
        'routes' => [
            'web' => 'api_docs',
            'upload' => 'open_api/upload',
            'scan_files' => [
                base_path('routes/web.php'),
            ],
            //中间件
            'middleware' => [
            ],
            'secret_key' => 'c4ca4238a0b923820dcc509a6f75849b',
        ],
        'path' => [
            'base_path' => storage_path('openApi'),
            'file_name' => 'openApi.json',
        ],
        'constants' => [
        ],
        'request_class_name' => \Illuminate\Http\Request::class,
        'request_response_map_file' => base_path('openApi/RequestWithResponse.php'),
    ],
];
