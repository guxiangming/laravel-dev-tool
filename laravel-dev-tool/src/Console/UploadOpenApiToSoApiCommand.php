<?php
declare(strict_types=1);

namespace DevTool\LaravelDevTool\Console;

use DevTool\LaravelDevTool\Service\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UploadOpenApiToSoApiCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'openApi:upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'upload openApi json';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info($this->description);
        $generator = new Generator();
        $content = File::get($generator->getFilePathName());
        $this->curl_post($generator->getUloadUrl(), json_encode([
            'secret_key' => $generator->getEncryptSecretKey(),
            'open_api' => json_decode($content, true),
        ]));
    }

    function curl_post($url, $post)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_HEADER, false);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Content-Type: application/json; charset=utf-8',
            ]
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);//提交方式为post，数据为json格式的。
        
        $result = curl_exec($ch);
        var_dump($result);
        curl_close($ch);
        return $result;
    }
}
