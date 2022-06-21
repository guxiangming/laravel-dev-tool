# 背景
- 在编写程序实现API接口时，首先会在系统代码中会维护一份请求参数约束与描述，该步骤与我们录入SOAPI流程存在重复冗余的现象。为减少重复低效的开发流程与增加API文档的易维护性，开发了一个效率工具，支持输出OPENAPI规范的数据组件，实现与SOAPI的同步
      
# 功能目标
- 实现Request过滤层数据复用
- 输出openApi规范数据实现SOAPI数据同步
- 可本地同步API文档数据至减少更新API文档的发布过程

# 安装步骤 
1. 检测composer.json是否限制中央服务器下载,如果限制则移除
```code
移除代码
{
 "packagist.org": false =>  "packagist.org": true
}
```
2. 安装到开发依赖(大于3.0.0)
```code
composer require dev-tool/laravel-dev-tool:^3.0.0 --dev
```
3. 发布配置
```code
php artisan vendor:publish --tag=dev-tool-config
```

# 配置说明
- 文件位置 config/dev-tool.php
```code
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
        /** 组件路由配置 */
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
        /** 映射路由配置 */
        'request_response_map_file' => base_path('openApi/RequestWithResponse.php'),
    ],
```

# 运行命令与策略

命令| 描述
---|---
php artisan openApi:generate | 将在本地config('open_api.path.base_path')目录生成缓存文件 
php artisan openApi:upload | 执行前先执行生成脚本,该功能上传本地API内容至config('open_api.host')域名服务器，本接口需要校验密钥本地APP_KEY需要与远程服务保持一致
php artisan openApi:register post:/api 接口名称 | openApi:register {route :请求方式+路由如 post:/suppliers} {name : 接口名称} 注册一个可实现OpenApi工具管控的路由，同时生成一个接口与响应层的关联关系文件config('open_api.request_response_map_file'),与接口响应输出文件

# 注意事项
1. 因涉及到每个研发人员进度差异问题，大家需把代码合并到开发分支进行操作，避免版本冲突与信息不一致问题

# todo
1. 响应层代码编辑规则过于麻烦需要简化
