<?php
return [
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
            "App\Models\BaseModel",
        ],
        'ignored_dir'=>[
            //需要排除自动注解的model
        ],
    ],
];