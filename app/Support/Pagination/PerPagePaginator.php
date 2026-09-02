<?php

namespace App\Support\Pagination;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class PerPagePaginator
{
    /** @var array<int, int> */
    private const ALLOWED_SIZES = [10, 30, 50, 100];

    public static function selection(Request $request, int $default = 10): string
    {
        $requested = strtolower(trim((string) $request->query('per_page', $default)));

        if ($requested === 'all') {
            return 'all';
        }

        $size = (int) $requested;

        return (string) (in_array($size, self::ALLOWED_SIZES, true) ? $size : $default);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function paginate(Builder $query, Request $request, int $default = 10): LengthAwarePaginator
    {
        $selection = self::selection($request, $default);

        if ($selection !== 'all') {
            return $query
                ->paginate((int) $selection)
                ->withQueryString();
        }

        $items = $query->get();

        return new LengthAwarePaginator(
            $items,
            $items->count(),
            max(1, $items->count()),
            1,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'page',
            ],
        );
    }
}
