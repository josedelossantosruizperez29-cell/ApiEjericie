<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ExerciseImage extends Command
{
    protected $signature = 'fit:exercise-image {slug} {version} {--apply} {--id= : UUID esperado en este entorno} {--sha256= : Huella del WebP aprobado} {--previous-url= : URL que se autoriza sustituir}';

    protected $description = 'Verifica una ilustración pública y opcionalmente la asocia al ejercicio';

    public function handle(): int
    {
        $base = rtrim((string) config('services.exercise_images.base_url'), '/');
        $slug = $this->argument('slug');
        $version = $this->argument('version');
        if (! preg_match('~^https://[a-z0-9.-]+(?::[0-9]{1,5})?/storage/v1/object/public/exercise-illustrations$~iD', $base)
            || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)
            || ! preg_match('/^[1-9][0-9]*$/D', $version)) {
            $this->error('Revisa la base HTTPS del bucket, el slug y la versión.');

            return self::FAILURE;
        }
        $exercise = Exercise::where('slug', $slug)->sole();
        if ($this->option('id') !== null && $this->option('id') !== $exercise->id) {
            $this->error('El UUID no corresponde al slug en este entorno.');

            return self::FAILURE;
        }
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
        if ($this->option('sha256') !== null && ! hash_equals(hash('sha256', $bytes), $this->option('sha256'))) {
            $this->error('El contenido descargado difiere del archivo aprobado.');

            return self::FAILURE;
        }
        if ($exercise->image_url !== $url) {
            $previous = $this->option('previous-url');
            if ($previous !== $exercise->image_url) {
                $this->error('La referencia existente difiere. Revisa el destino antes de autorizar el reemplazo.');

                return self::FAILURE;
            }
            if ($this->option('apply') && Exercise::whereKey($exercise->id)->where('slug', $slug)
                ->where('image_url', $previous)->update(['image_url' => $url]) !== 1) {
                $this->error('El ejercicio cambió durante la verificación.');

                return self::FAILURE;
            }
        }
        $this->info($url);

        return self::SUCCESS;
    }
}
