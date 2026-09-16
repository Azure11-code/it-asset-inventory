<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multi-keyword search for a model.
 *
 * A model declares every field a keyword may hit in $searchable. Each entry is:
 *
 *   'asset_tag'                        a column on the model's own table
 *   'brand.name'                       a column on a related model (any depth)
 *   'concat:first_name,last_name'      several own columns joined by a space,
 *                                      so "juan dela cruz" matches a full name
 *   'currentHolder.concat:first_name,last_name'   the same, on a relation
 *
 * The search term is split into keywords ("quoted phrases" stay together) and
 * EVERY keyword must match at least one of the fields. That is what lets
 * "dell laptop juan" find Juan's Dell laptop even though the three words live
 * in three different tables.
 */
trait Searchable
{
    /** Keywords beyond this are ignored, to keep the generated SQL sane. */
    protected static int $searchTokenLimit = 8;

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $tokens = static::searchTokens($term);

        foreach ($tokens as $token) {
            // AND between keywords, OR between the fields each keyword may hit.
            $query->where(function (Builder $w) use ($token) {
                foreach (static::searchableFields() as $field) {
                    static::applySearchField($w, $field, $token);
                }
            });
        }

        return $query;
    }

    /** @return string[] */
    public static function searchableFields(): array
    {
        return static::$searchable ?? [];
    }

    /**
     * Splits a raw search box value into keywords. Double-quoted runs are kept
     * as one keyword so an exact phrase can still be searched.
     *
     * @return string[]
     */
    public static function searchTokens(?string $term): array
    {
        $term = trim((string) $term);
        if ($term === '') {
            return [];
        }

        preg_match_all('/"([^"]+)"|(\S+)/', $term, $matches, PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $m) {
            $token = trim($m[1] !== '' ? $m[1] : ($m[2] ?? ''));
            if ($token !== '') {
                $tokens[mb_strtolower($token)] = $token;
            }
        }

        return array_slice(array_values($tokens), 0, static::$searchTokenLimit);
    }

    /** Adds one OR branch per searchable field for a single keyword. */
    protected static function applySearchField(Builder $query, string $field, string $token): void
    {
        $segments = explode('.', $field);
        $spec     = array_pop($segments);
        $relation = implode('.', $segments);

        if ($relation === '') {
            static::orWhereMatches($query, $spec, $token);

            return;
        }

        $query->orWhereHas($relation, function (Builder $related) use ($spec, $token) {
            // whereHas starts a fresh constraint group, so the first condition
            // must be a where() rather than an orWhere() to bind correctly.
            $related->where(fn (Builder $w) => static::orWhereMatches($w, $spec, $token));
        });
    }

    /** Applies `<column> LIKE %token%` — or the CONCAT_WS form — as an OR. */
    protected static function orWhereMatches(Builder $query, string $spec, string $token): void
    {
        $like  = '%' . static::escapeLike($token) . '%';
        $table = $query->getModel()->getTable();

        if (str_starts_with($spec, 'concat:')) {
            $columns = array_filter(array_map('trim', explode(',', substr($spec, 7))));
            if ($columns === []) {
                return;
            }
            $qualified = array_map(fn ($c) => "`{$table}`.`{$c}`", $columns);
            $query->orWhereRaw('CONCAT_WS(\' \', ' . implode(', ', $qualified) . ') LIKE ?', [$like]);

            return;
        }

        // Always qualify: index queries may join other tables that share a
        // column name (e.g. assets.description vs categories.description).
        $query->orWhere("{$table}.{$spec}", 'like', $like);
    }

    /** Stops user input like "50%" or "a_b" from acting as LIKE wildcards. */
    protected static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
