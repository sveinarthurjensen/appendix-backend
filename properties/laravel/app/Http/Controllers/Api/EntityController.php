<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\EntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Generisk, Base44-kompatibelt entitets-API.
 *
 *  GET    /api/entities/{entity}?q={json}&sort=-created_date&limit=50&skip=0&fields=a,b
 *  GET    /api/entities/{entity}/{id}
 *  POST   /api/entities/{entity}            body = objekt (create) eller liste (bulkCreate)
 *  PATCH  /api/entities/{entity}/{id}
 *  DELETE /api/entities/{entity}/{id}
 *
 * Svarene har samme form som Base44-SDK-en forventer, slik at frontend-shimen
 * kan bytte backend per entitet uten endringer i sidene.
 */
class EntityController extends Controller
{
    private function modelClass(string $entity): string
    {
        $class = EntityRegistry::model($entity);
        abort_if(!$class, 404, "Ukjent entitet: $entity");
        return $class;
    }

    public function index(Request $request, string $entity): JsonResponse
    {
        $class = $this->modelClass($entity);
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', $class);

        $filter = $request->has('q') ? json_decode($request->query('q'), true, 512, JSON_THROW_ON_ERROR) : [];
        $limit  = min((int) $request->query('limit', 50), 1000);
        $skip   = max((int) $request->query('skip', 0), 0);

        $q = $class::query()->visibleTo($user)->base44Filter($filter ?? []);

        if ($sort = $request->query('sort')) {
            foreach (explode(',', $sort) as $s) {
                $dir = str_starts_with($s, '-') ? 'desc' : 'asc';
                $q->orderBy(ltrim($s, '-+'), $dir);
            }
        } else {
            $q->orderBy('created_date', 'desc');
        }

        if ($fields = $request->query('fields')) {
            $q->select(array_unique(array_merge(['id', 'app_id'], explode(',', $fields))));
        }

        $rows = $q->skip($skip)->take($limit)->get()->map->toBase44Array();
        return response()->json($rows);
    }

    public function show(Request $request, string $entity, string $id): JsonResponse
    {
        $class = $this->modelClass($entity);
        $model = $class::query()->findOrFail($id);
        Gate::forUser($request->user())->authorize('view', $model);
        return response()->json($model->toBase44Array());
    }

    public function store(Request $request, string $entity): JsonResponse
    {
        $class = $this->modelClass($entity);
        Gate::forUser($request->user())->authorize('create', $class);

        $payload = $request->json()->all();
        $isBulk = array_is_list($payload);
        $items = $isBulk ? $payload : [$payload];

        $created = DB::transaction(function () use ($class, $items) {
            return array_map(function (array $attrs) use ($class) {
                $this->validateAttributes($class, $attrs, creating: true);
                /** @var Model $m */
                $m = new $class();
                $m->fill($attrs);
                if (!empty($attrs['id'])) {
                    $m->id = $attrs['id']; // import med bevart Base44-ID
                }
                $m->save();
                return $m->toBase44Array();
            }, $items);
        });

        return response()->json($isBulk ? $created : $created[0], 201);
    }

    public function update(Request $request, string $entity, string $id): JsonResponse
    {
        $class = $this->modelClass($entity);
        $model = $class::query()->findOrFail($id);
        Gate::forUser($request->user())->authorize('update', $model);

        $attrs = $request->json()->all();
        $this->validateAttributes($class, $attrs, creating: false);
        $model->fill($attrs)->save();
        return response()->json($model->fresh()->toBase44Array());
    }

    public function destroy(Request $request, string $entity, string $id): JsonResponse
    {
        $class = $this->modelClass($entity);
        $model = $class::query()->findOrFail($id);
        Gate::forUser($request->user())->authorize('delete', $model);
        $model->delete(); // soft delete – «versjoner, ikke slett»
        return response()->json(['success' => true, 'id' => $id]);
    }

    /** Valideringsregler utledet av skjemaet: påkrevde felt + enum-verdier + bare kjente felt */
    private function validateAttributes(string $class, array $attrs, bool $creating): void
    {
        $fillable = (new $class())->getFillable();
        $unknown = array_diff(array_keys($attrs), $fillable, ['id']);
        abort_if($unknown, 422, 'Ukjente felt: ' . implode(', ', $unknown));

        $rules = [];
        if ($creating) {
            foreach ($class::REQUIRED as $f) {
                $rules[$f] = ['required'];
            }
        }
        foreach ($class::ENUMS as $f => $allowed) {
            $rules[$f][] = 'nullable';
            $rules[$f][] = Rule::in($allowed);
        }
        validator($attrs, $rules)->validate();
    }
}
