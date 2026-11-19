<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Remembers how a list was last being viewed.
 *
 * The search text, filters and page number are kept in the session under a
 * per-list key, so "Back to assets", "Back to list" and the sidebar links can
 * return the user to the list they left rather than the default one.
 *
 * Only values that are actually set are stored, and the page only when it is
 * past the first — so a cleared list stores nothing and the next visit opens
 * the full list.
 */
trait RemembersListQuery
{
    protected function rememberListQuery(Request $request, string $sessionKey, ?int $currentPage = null): void
    {
        $query = collect($request->only($this->listQueryKeys()))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if ($currentPage !== null && $currentPage > 1) {
            $query['page'] = $currentPage;
        }

        $query ? session([$sessionKey => $query]) : session()->forget($sessionKey);
    }

    /** Query-string keys worth remembering. Controllers may override. */
    protected function listQueryKeys(): array
    {
        return ['search', 'sort', 'direction'];
    }
}
