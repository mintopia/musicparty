<?php

namespace App\Console\Commands;

use App\Support\OpenApi\OpenApiGenerator;
use Illuminate\Console\Command;

class GenerateOpenApi extends Command
{
    protected $signature = 'openapi:generate {--check : Exit non-zero when the committed document differs from the generated one}';

    protected $description = 'Generate the OpenAPI document from routes, form requests and API resources';

    public function handle(OpenApiGenerator $generator): int
    {
        $json = $generator->toJson($generator->generate());
        $path = base_path('openapi/openapi.json');
        $current = is_file($path) ? (string) file_get_contents($path) : null;

        if ($this->option('check')) {
            if ($current !== $json) {
                $this->error('openapi/openapi.json is out of date. Run php artisan openapi:generate.');

                return self::FAILURE;
            }

            $this->info('openapi/openapi.json is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $json);
        $this->info('Wrote openapi/openapi.json');

        return self::SUCCESS;
    }
}
