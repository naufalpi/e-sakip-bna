<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_indexes_support_showing_all_filtered_rows(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'super_admin')->value('id')]);

        $pages = [
            ['route' => 'renstra-opd.index', 'component' => 'RenstraOpd/Index', 'paginator' => 'renstras'],
            ['route' => 'renja-opd.index', 'component' => 'RenjaOpd/Index', 'paginator' => 'items'],
            ['route' => 'rka-opd.index', 'component' => 'RkaOpd/Index', 'paginator' => 'items'],
            ['route' => 'dpa-opd.index', 'component' => 'DpaOpd/Index', 'paginator' => 'items'],
            ['route' => 'perjanjian-kinerja.index', 'component' => 'Kinerja/PerjanjianKinerja/Index', 'paginator' => 'items'],
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)
                ->get(route($page['route'], ['per_page' => 'all']))
                ->assertOk()
                ->assertInertia(fn (Assert $inertia) => $inertia
                    ->component($page['component'])
                    ->where('filters.per_page', 'all')
                    ->where("{$page['paginator']}.current_page", 1)
                    ->where("{$page['paginator']}.last_page", 1)
                );
        }
    }
}
