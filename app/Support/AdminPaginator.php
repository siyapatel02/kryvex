<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminPaginator
{
    public static function paginate(Collection $items, Request $request, int $perPage = 10, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = max((int) $request->query($pageName, 1), 1);
        $total = $items->count();
        $lastPage = max((int) ceil($total / $perPage), 1);

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => $pageName,
            ]
        );
    }
}
