<?php

namespace App\Services;

use App\Models\CarModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Generations, modifications, specs and photos of a CarAPI (geoapi.ge) model. Every list is cached,
 * so the app can browse the catalog without spending the monthly request quota.
 */
class CarTrims
{
    private const DAY = 60 * 60 * 24;

    private const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(private CarCatalog $catalog) {}

    /** Generations of a model, optionally only those built in $year. null: unknown brand/model. */
    public function generations(int $brandId, int $modelId, ?int $year): ?array
    {
        $model = $this->catalog->remoteModel($brandId, $modelId);
        if (! $model) {
            return null;
        }

        // geoapi.ge has checked photos for models first; a generation without its own shows the model's.
        $modelPhoto = $this->photoLink($modelId, null, $model['photo_url'] ?? null);

        return collect($this->catalog->remoteGenerations($modelId))
            ->filter(fn (array $row) => $year === null || $this->covers($row, $year))
            ->map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) ($row['display_label'] ?? $row['name'] ?? ''),
                'year_start' => is_numeric($row['year_start'] ?? null) ? (int) $row['year_start'] : null,
                'year_end' => is_numeric($row['year_end'] ?? null) ? (int) $row['year_end'] : null,
                'body_type' => $row['body_type'] ?? null,
                'photo' => $this->photoLink($modelId, (int) $row['id'], $row['photo_url'] ?? null) ?? $modelPhoto,
            ])
            ->filter(fn (array $row) => $row['name'] !== '')
            ->values()->all();
    }

    /** Modifications of a generation of the given model. null: the generation is not this model's. */
    public function modifications(int $modelId, int $generationId, ?int $year): ?array
    {
        if (! $this->generation($modelId, $generationId)) {
            return null;
        }
        $rows = Cache::remember('carapi.modifications.'.$generationId, self::DAY,
            fn () => $this->catalog->pages('/generations/'.$generationId.'/modifications', ['include' => 'specs']));

        return collect($rows)
            ->filter(fn (array $row) => $year === null || $this->covers($row, $year))
            ->map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
                'engine_volume_cc' => $row['engine_volume_cc'] ?? null,
                'transmission' => $row['transmission'] ?? null,
                'fuel' => $row['specs']['engine']['fuel'] ?? null,
                'power_hp' => $row['specs']['engine']['power_hp'] ?? null,
                'drive' => $row['specs']['transmission']['drive'] ?? null,
                'year_start' => $row['year_start'] ?? null,
                'year_end' => $row['year_end'] ?? null,
            ])
            ->filter(fn (array $row) => $row['name'] !== '')
            ->values()->all();
    }

    /** One modification with its specs and equipment. */
    public function modification(int $id): ?array
    {
        return Cache::remember('carapi.modification.'.$id, self::DAY, function () use ($id) {
            $item = $this->catalog->item('/modifications/'.$id);
            if (! $item) {
                return null;
            }

            return $item + [
                'specs' => $this->catalog->item('/modifications/'.$id.'/specs'),
                'equipment' => $this->catalog->item('/modifications/'.$id.'/equipment'),
            ];
        });
    }

    /**
     * user_cars columns for a picked generation/modification of a remote model. Names, body type and photo
     * are read from the catalog, never taken from the client.
     *
     * @throws ValidationException
     */
    public function attributes(int $brandId, int $modelId, ?int $generationId, ?int $modificationId): array
    {
        $generation = $generationId ? $this->generation($modelId, $generationId) : null;
        if ($generationId && ! $generation) {
            throw ValidationException::withMessages(['generation_id' => __('cars.generation_invalid')]);
        }
        $modification = $modificationId && $generationId ? $this->modificationRow($generationId, $modificationId) : null;
        if ($modificationId && ! $modification) {
            throw ValidationException::withMessages(['modification_id' => __('cars.modification_invalid')]);
        }
        $model = $this->catalog->remoteModel($brandId, $modelId);
        $photo = $this->photoLink($modelId, $generationId, $generation['photo_url'] ?? null)
            ?? $this->photoLink($modelId, null, $model['photo_url'] ?? null);

        return [
            'generation_id' => $generationId,
            'generation_name' => $generation ? mb_substr((string) ($generation['display_label'] ?? $generation['name'] ?? ''), 0, 128) : null,
            'modification_id' => $modification ? $modificationId : null,
            'modification_name' => $modification ? mb_substr((string) ($modification['name'] ?? ''), 0, 128) : null,
            'body_type' => $generation ? (mb_substr((string) ($generation['body_type'] ?? ''), 0, 32) ?: null) : null,
            // Stored as a path, so a domain change does not break saved cars.
            'photo' => $photo ? (string) parse_url($photo, PHP_URL_PATH).(($q = parse_url($photo, PHP_URL_QUERY)) ? '?'.$q : '') : null,
        ];
    }

    /** Raw generation row of a model, from the cached list. */
    public function generation(int $modelId, int $generationId): ?array
    {
        return collect($this->catalog->remoteGenerations($modelId))->first(fn (array $row) => (int) $row['id'] === $generationId);
    }

    public function modificationRow(int $generationId, int $modificationId): ?array
    {
        $rows = Cache::get('carapi.modifications.'.$generationId)
            ?? $this->catalog->pages('/generations/'.$generationId.'/modifications', ['include' => 'specs']);

        return collect($rows)->first(fn (array $row) => (int) $row['id'] === $modificationId);
    }

    /**
     * Photo bytes of a model, or of one of its generations. The upstream photo_url needs the secret key,
     * so the server fetches it once, keeps it on disk and serves it to the app. null: no checked photo.
     *
     * @return array{body: string, type: string}|null
     */
    public function photo(int $modelId, ?int $generationId): ?array
    {
        $key = 'carapi-photos/'.$modelId.'-'.($generationId ?? 0);
        $disk = Storage::disk('local');
        if ($disk->exists($key) && $disk->exists($key.'.type')) {
            return ['body' => $disk->get($key), 'type' => $disk->get($key.'.type')];
        }
        if (Cache::has($key.'.missing')) {
            return null;
        }

        $url = $this->upstreamPhoto($modelId, $generationId);
        if (! $url) {
            return null;
        }
        $response = $this->catalog->request()->get($url);
        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if ($response->status() === 404) {
            // No checked photo for this model: ask again tomorrow, not on every app screen.
            Cache::put($key.'.missing', true, self::DAY);

            return null;
        }
        if (! $response->successful() || ! in_array($type, self::PHOTO_TYPES, true) || $response->body() === '') {
            throw new RuntimeException('CarAPI photo HTTP '.$response->status());
        }
        $disk->put($key, $response->body());
        $disk->put($key.'.type', $type);

        return ['body' => $response->body(), 'type' => $type];
    }

    /** Our own photo link for a catalog row that has a photo upstream. */
    public function photoLink(int $modelId, ?int $generationId, mixed $photoUrl): ?string
    {
        if (! CarCatalog::httpsUrl($photoUrl)) {
            return null;
        }

        return url('/api/cars/models/'.$modelId.'/photo'.($generationId ? '?generation='.$generationId : ''));
    }

    /**
     * The generation's photo_url when it is on the CarAPI host, else the model photo endpoint. The route is
     * public (image tags send no token), so only ids we already know may cost an upstream request:
     * a model or generation from a list the app just loaded, or a model a user has saved.
     */
    private function upstreamPhoto(int $modelId, ?int $generationId): ?string
    {
        $base = $this->catalog->base();
        $modelPhoto = $base.'/models/'.$modelId.'/photo';
        if ($generationId === null) {
            $known = Cache::has('carapi.generations.'.$modelId)
                || CarModel::query()->where('source', CarCatalog::REMOTE)->where('external_model_id', $modelId)->exists();

            return $known ? $modelPhoto : null;
        }

        $row = collect(Cache::get('carapi.generations.'.$modelId, []))->first(fn (array $row) => (int) $row['id'] === $generationId);
        if (! $row) {
            return null;
        }
        $url = $row['photo_url'] ?? null;
        // Never send our secret key to another host.
        $sameHost = is_string($url) && str_starts_with($url, 'https://')
            && parse_url($url, PHP_URL_HOST) === parse_url($base, PHP_URL_HOST);

        return $sameHost ? $url : $modelPhoto;
    }

    private function covers(array $row, int $year): bool
    {
        $start = $row['year_start'] ?? null;
        $end = $row['year_end'] ?? null;

        return (! is_numeric($start) || (int) $start <= $year) && (! is_numeric($end) || (int) $end >= $year);
    }
}
