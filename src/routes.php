<?php

use Illuminate\Support\Facades\Route;

Route::any(config('dev-tool.open_api.routes.web') . '/{jsonFile?}', [
    'middleware' => config('dev-tool.oepn_api.routes.middleware', []),
    'uses' => '\DevTool\LaravelDevTool\Http\Controllers\OpenApiController@json',
]);

Route::post(config('dev-tool.open_api.routes.upload'), [
    'middleware' => config('dev-tool.oepn_api.routes.middleware', []),
    'uses' => '\DevTool\LaravelDevTool\Http\Controllers\OpenApiController@upload',
]);


