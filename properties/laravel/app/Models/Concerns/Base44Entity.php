<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Felles oppførsel for alle entiteter som er migrert fra Base44:
 *  - streng-ID (Base44-ID beholdes ved import, ULID på nye rader)
 *  - created_date/updated_date i stedet for created_at/updated_at
 *  - created_by (e-post) settes automatisk
 *  - app_id settes fra modellens APP_ID-konstant og brukes som tenant-scope
 *  - scopeVisibleTo($user): rad-nivå-filter fra RLS (OWNER_FIELDS)
 *  - scopeBase44Filter(array): MongoDB-lignende filter som Base44-SDK-en bruker
 */
trait Base44Entity
{
    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    public $incrementing = false;
    protected $keyType = 'string';

    public static function bootBase44Entity(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = strtolower((string) Str::ulid());
            }
            if (empty($model->app_id)) {
                $model->app_id = static::APP_ID;
            }
            if (empty($model->created_by) && ($u = auth()->user())) {
                $model->created_by = $u->email;
            }
        });

        // Tenant-scope: én backend, flere apper
        static::addGlobalScope('app', function (Builder $q) {
            $q->where($q->getModel()->getTable() . '.app_id', static::APP_ID);
        });
    }

    /** Rader brukeren får se iht. RLS. Admin ser alt. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $q;
        }
        $owner = static::OWNER_FIELDS ?? [];
        if (empty($owner)) {
            // Ingen rad-regel → policy avgjør (viewAny); tom liste om den sier nei
            return $user->can('viewAny', static::class) ? $q : $q->whereRaw('1 = 0');
        }
        return $q->where(function (Builder $w) use ($owner, $user) {
            foreach ($owner as $field => $attr) {
                $w->orWhere($field, $user->{$attr});
            }
        });
    }

    /**
     * Base44-filter → Eloquent.
     * Støtter: {felt: verdi}, {felt: {$in|$nin|$gt|$gte|$lt|$lte|$ne|$exists|$regex}}, {$or: [...]}, {$and: [...]}
     */
    public function scopeBase44Filter(Builder $q, array $filter): Builder
    {
        foreach ($filter as $key => $value) {
            if ($key === '$or' || $key === '$and') {
                $boolean = $key === '$or' ? 'or' : 'and';
                $q->where(function (Builder $w) use ($value, $boolean) {
                    foreach ($value as $sub) {
                        $w->where(fn (Builder $x) => $x->base44Filter($sub), null, null, $boolean);
                    }
                });
                continue;
            }
            $col = $this->qualifyFilterColumn($key);
            if (is_array($value) && array_keys($value) !== range(0, count($value) - 1)) {
                foreach ($value as $op => $v) {
                    match ($op) {
                        '$in'     => $q->whereIn($col, (array) $v),
                        '$nin'    => $q->whereNotIn($col, (array) $v),
                        '$gt'     => $q->where($col, '>', $v),
                        '$gte'    => $q->where($col, '>=', $v),
                        '$lt'     => $q->where($col, '<', $v),
                        '$lte'    => $q->where($col, '<=', $v),
                        '$ne'     => $q->where($col, '!=', $v),
                        '$exists' => $v ? $q->whereNotNull($col) : $q->whereNull($col),
                        '$regex'  => $q->where($col, '~*', $v),
                        '$contains' => $q->whereJsonContains($col, $v),
                        default   => throw new \InvalidArgumentException("Ustøttet filteroperator $op"),
                    };
                }
            } elseif (is_null($value)) {
                $q->whereNull($col);
            } else {
                $q->where($col, $value);
            }
        }
        return $q;
    }

    /** "metadata.kilde" → metadata->kilde (JSONB) */
    protected function qualifyFilterColumn(string $key): string
    {
        if (str_contains($key, '.')) {
            [$root, $rest] = explode('.', $key, 2);
            return $root . '->' . str_replace('.', '->', $rest);
        }
        return $key;
    }

    /** Samme JSON-form som Base44 returnerer */
    public function toBase44Array(): array
    {
        $a = $this->toArray();
        unset($a['deleted_at']);
        return $a;
    }
}
