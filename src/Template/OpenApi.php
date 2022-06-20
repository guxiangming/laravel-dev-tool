<?php
declare(strict_types=1);

namespace DevTool\LaravelDevTool\Template;

class OpenApi
{
    /**
     *  请求头参数
     */
    public const HEADER_PARAMETERS = [
        'name' => '',
        'in' => 'header',
        'description' => 'header',
        'required' => true,
        'type' => '',
        'default' => '',
    ];

    /**
     *  请求头参数
     */
    public const BODY_PARAMETERS = [
        'in' => 'body',
        'name' => 'req',
        'description' => 'req',
        'required' => true,
        'schema' => [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ],
    ];

    /**
     *  url访问形式传参
     */
    public const GET_REQUEST = [
        'name' => '',
        'in' => 'query',
        'description' => '',
        'required' => true,
        'title' => '',
        'schema' => [
            'type' => '',
            'default' => '',
        ],
    ];

    /**
     * 请求体传参
     */
    public const POST_REQUEST = [
        'content' => [
            'application/json' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
        ],
    ];

    /**
     * 响应体
     */
    public const RESPONSE = [
        200 => [
            'description' => 'OK',
            'schema' => [],
        ],
    ];

    /**
     * 请求参数类型
     */
    public const REQUEST_PARAMS_TYPE = [
        'string', 'integer', 'array', 'object', 'boolean',
    ];

    /**
     * 响应参数类型
     */
    public const RESPONSE_PARAMS_TYPE = [
        'string', 'integer', 'array', 'object', 'boolean',
    ];
}
