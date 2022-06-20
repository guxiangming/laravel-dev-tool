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
        /** 协议类型 */
        'swagger_version' => '2.0',
        /** 协议版本号 */
        'info_version' => '2.6.4.1-SNAPSHOT',
        /** 【必填】api的域名与本地上传内容路由关联 */
        'host' => '',
        /** 项目描述 */
        'description' => 'openApi',
        /** 项目名称 */
        'title' => env('APP_NAME'),
        /** 联系人描述用,符合分割 */
        'operator' => '',
        /** 许可单位 */
        'license' => '',
        /** 【选填】仓库版本 */
        'repository_url' => 'gitlab',
        /** 组间路由配置 */
        'routes' => [
            /** 文档输出路由 */
            'web' => 'open_api/api_docs',
            /** 本地上传API路由 */
            'upload' => 'open_api/upload',
            /** 扫描项目路由文件 */
            'scan_files' => [
                base_path('routes/web.php'),
            ],
            /** 组件路由可配置中间件 */
            'middleware' => [
            ],
            /** 本地上传API验证路由 */
            'secret_key' => 'c4ca4238a0b923820dcc509a6f75849b',
        ],
        'path' => [
            /** 缓存文件的构建目录 */
            'base_path' => storage_path('openApi'),
            /** 生成的文件名 */
            'file_name' => 'openApi.json',
        ],
        'constants' => [
        ],
        /** Request基类 */
        'request_class_name' => \Illuminate\Http\Request::class,
        /** 映射路由配置文件 */
        'request_response_map_file' => base_path('openApi/RequestWithResponse.php'),
    ],
];
