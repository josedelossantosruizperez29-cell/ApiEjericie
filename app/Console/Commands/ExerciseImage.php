<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ExerciseImage extends Command
{
    protected $signature = 'fit:exercise-image {slug} {version} {--apply}';

    protected $description = 'Verifica una ilustración pública y opcionalmente la asocia al ejercicio';

    public function handle(): int
    {
        $base = rtrim((string) config('services.exercise_images.base_url'), '/');
        $slug = $this->argument('slug');
        $version = $this->argument('version');
        if (! preg_match('~^https://[a-z0-9.-]+/storage/v1/object/public/exercise-illustrations$~iD', $base)
            || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)
            || ! preg_match('/^[1-9][0-9]*$/D', $version)) {
            $this->error('Revisa la base HTTPS del bucket, el slug y la versión.');

            return self::FAILURE;
        }
        $exercise = Exercise::where('slug', $slug)->sole();
        $url = "$base/exercises/$slug-v$version.webp";
        $response = Http::withoutRedirecting()->timeout(12)->get($url);
        $bytes = $response->body();
        $size = @getimagesizefromstring($bytes);
        if (! $response->ok() || $response->header('Content-Type') !== 'image/webp'
            || strlen($bytes) > 524288 || ($size['mime'] ?? null) !== 'image/webp'
            || max($size[0], $size[1]) > 1600) {
            $this->error('El objeto debe responder 200 con un WebP de hasta 512 KiB y 1600 px.');

            return self::FAILURE;
        }
        if ($this->option('apply')) {
            $exercise->image_url = $url;
            $exercise->save();
        }
        $this->info($url);

        return self::SUCCESS;
    }
}
