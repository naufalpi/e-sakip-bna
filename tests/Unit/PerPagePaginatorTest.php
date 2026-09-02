<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Pagination\PerPagePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PerPagePaginatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_supported_page_sizes_and_rejects_invalid_values(): void
    {
        $this->assertSame('30', PerPagePaginator::selection(Request::create('/users', 'GET', ['per_page' => '30'])));
        $this->assertSame('100', PerPagePaginator::selection(Request::create('/users', 'GET', ['per_page' => '100'])));
        $this->assertSame('all', PerPagePaginator::selection(Request::create('/users', 'GET', ['per_page' => 'all'])));
        $this->assertSame('10', PerPagePaginator::selection(Request::create('/users', 'GET', ['per_page' => '999'])));
    }

    public function test_it_paginates_by_selected_size(): void
    {
        User::factory()->count(35)->create();
        $request = Request::create('/users', 'GET', ['per_page' => '30']);
        $this->app->instance('request', $request);

        $paginator = PerPagePaginator::paginate(User::query()->orderBy('id'), $request);

        $this->assertCount(30, $paginator->items());
        $this->assertSame(30, $paginator->perPage());
        $this->assertSame(35, $paginator->total());
        $this->assertSame(2, $paginator->lastPage());
    }

    public function test_all_selection_returns_every_filtered_row_on_one_page(): void
    {
        $includedIds = User::factory()->count(12)->create()->modelKeys();
        User::factory()->count(5)->create();
        $request = Request::create('/users', 'GET', ['per_page' => 'all']);

        $paginator = PerPagePaginator::paginate(
            User::query()->whereKey($includedIds)->orderBy('id'),
            $request,
        );

        $this->assertCount(12, $paginator->items());
        $this->assertSame(12, $paginator->total());
        $this->assertSame(1, $paginator->lastPage());
        $this->assertSame('all', $paginator->getOptions()['query']['per_page']);
    }
}
