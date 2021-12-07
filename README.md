# 背景
- 在编写程序过程中，部分ide会检测标注提示所使用的类属性对象是否存在，如果不存在则警告错误，这个现象在laravel-model对象调用较为常见。在一般情况下去优化ide提示，需要进行手动写入注解，这样的步骤机械而重复。所以希望能拥有脚本能自动生成与更新文件注解。
      
# 需求调查
- 经过查找php生态已经有可用的注解生成工具，但功能存在缺陷，仅支持对数据库字段进行注解。针对复杂的laravel-model需要从关联模型、表字段、attribute、casts、scope等特性考虑。

# 安装步骤
1. 检测composer.json是否限制中央服务器下载,如果限制则需要开启 (后期考虑加入到内网环境)
```code
 "packagist.org": false =>  "packagist.org": true
```
2. 安装到开发依赖

composer require dev-tool/laravel-dev-tool --dev

3. 发布配置
php artisan vendor:publish --tag=dev-tool-config


# 配置说明
- 文件位置 config/dev-tool.php
```code
 /**
     *  Model注解批量管理类
     */
    'model_annotation_helper' =>[
        'auto_model'=>[
            //自动注解的model
        ],
        'auto_dir'=>[
            //自动注解的目录
        ],
        'ignored_model'=>[
            //需要排除自动注解的model
        ],
        'ignored_dir'=>[
            //需要排除自动注解的model
        ],
    ],
```

# 运行命令
- php artisan model-annotation-helper:generate --model=""  //model参数填写 文件命名空间+类名 

1. php artisan model-annotation-helper:generate //根据读取配置文件生产注解
2. php artisan model-annotation-helper:generate --model="" //根据读取配置文件+指定类生成注解 
3. php artisan model-annotation-helper:generate --dir="" //根据读取配置文件+指定目录生成注解
4. php artisan model-annotation-helper:generate --dir="" --model="" //根据读取配置文件+指定目录生成注解+指定类生成注解 
5. php artisan model-annotation-helper:generate --ignored-config=true --model="" --dir="" //忽略config配置只生成指定位置注解

# 注
1. 生成的注解中的类中如果不存在引入情况，不会自动添加
2. 每次扫描生成新的注解会兼容用户自定义的注释

# todo
1. 兼容内网环境
