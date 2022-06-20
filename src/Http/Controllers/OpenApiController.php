<?php

namespace DevTool\LaravelDevTool\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use DevTool\LaravelDevTool\Service\Generator;
use Illuminate\Routing\Controller as BaseController;

class OpenApiController extends BaseController
{
    public function json(string $file = null)
    {
        $fileName = config('dev-tool.open_api.path.file_name', 'openApi.json');
        $filePath = config('dev-tool.open_api.path.base_path') . '/' . $fileName;

        if (!File::exists($filePath)) {
            try {
                Generator::generateDocs();
            } catch (\Exception $e) {
                Log::error($e);
                abort(
                    404,
                    sprintf(
                        'generate documentation failed: "%s". Please make sure directory is writable. Error: %s',
                        $filePath,
                        $e->getMessage()
                    )
                );
            }
        }
        $content = File::get($filePath);

        return Response::make($content, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function upload(\Illuminate\Http\Request $request, Generator $generator)
    {
//        nginx
//        client_max_body_size 100M;
//        client_header_buffer_size 512k;
//        large_client_header_buffers 4 512k;
        $generator->upload($request->all());

        return Response::json('ok');
    }
}
