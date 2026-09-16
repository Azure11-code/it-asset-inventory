<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Covers the multi-keyword search: every keyword must match, keywords may land
 * in different tables, and quoted text is treated as one phrase.
 *
 * Uses DatabaseTransactions (not RefreshDatabase) so running the suite never
 * wipes the working database — each test rolls back when it finishes.
 */
class SearchTest extends TestCase
{
    use DatabaseTransactions;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $brand      = Brand::create(['name' => 'Dellzzz', 'slug' => 'dellzzz-test']);
        $category   = Category::create(['name' => 'Laptopzzz', 'slug' => 'laptopzzz-test', 'prefix' => 'LTZ']);
        $department = Department::create(['code' => 'ITZZZ', 'name' => 'Infotechzzz']);
        $location   = Location::create(['name' => 'Warehouzzz', 'building' => 'Annexzzz']);

        $employee = Employee::create([
            'employee_no'   => 'EMPZZZ-1',
            'first_name'    => 'Juanzzz',
            'middle_name'   => 'Pzzz',
            'last_name'     => 'Cruzzzz',
            'position'      => 'Technicianzzz',
            'department_id' => $department->id,
            'location_id'   => $location->id,
            'status'        => 'active',
        ]);

        $this->asset = Asset::create([
            'asset_tag'           => 'ZZZ-LT-0001',
            'serial_number'       => 'SNZZZ12345',
            'model'               => 'Latitudezzz 5420',
            'description'         => 'Frontlinezzz unit',
            'vendor'              => 'Vendorzzz Inc',
            'brand_id'            => $brand->id,
            'category_id'         => $category->id,
            'department_id'       => $department->id,
            'current_holder_id'   => $employee->id,
            'current_location_id' => $location->id,
            'current_status'      => 'assigned',
        ]);
    }

    private function search(string $term): array
    {
        return Asset::search($term)->pluck('assets.id')->all();
    }

    public function test_it_finds_an_asset_by_a_single_keyword_on_its_own_table(): void
    {
        $this->assertContains($this->asset->id, $this->search('SNZZZ12345'));
        $this->assertContains($this->asset->id, $this->search('Vendorzzz'));
    }

    public function test_it_finds_an_asset_by_a_keyword_that_lives_on_a_relation(): void
    {
        // Brand, category, holder, location and department are all separate tables.
        $this->assertContains($this->asset->id, $this->search('Dellzzz'));
        $this->assertContains($this->asset->id, $this->search('Laptopzzz'));
        $this->assertContains($this->asset->id, $this->search('Cruzzzz'));
        $this->assertContains($this->asset->id, $this->search('Warehouzzz'));
        $this->assertContains($this->asset->id, $this->search('Infotechzzz'));
    }

    public function test_every_keyword_must_match_and_they_may_come_from_different_tables(): void
    {
        // brand + category + holder surname — three words, three tables.
        $this->assertContains($this->asset->id, $this->search('Dellzzz Laptopzzz Cruzzzz'));

        // Adding a keyword nothing matches must drop the asset from the results.
        $this->assertNotContains($this->asset->id, $this->search('Dellzzz Laptopzzz nosuchkeywordzzz'));
    }

    public function test_it_matches_a_full_name_written_as_separate_words(): void
    {
        $this->assertContains($this->asset->id, $this->search('Juanzzz Cruzzzz'));
        $this->assertContains($this->asset->id, $this->search('"Juanzzz Pzzz Cruzzzz"'));
    }

    public function test_keyword_order_does_not_matter(): void
    {
        $this->assertContains($this->asset->id, $this->search('Cruzzzz Dellzzz'));
        $this->assertContains($this->asset->id, $this->search('Dellzzz Cruzzzz'));
    }

    public function test_wildcards_typed_by_the_user_are_treated_as_plain_text(): void
    {
        // Without escaping, "%" would match every row.
        $this->assertNotContains($this->asset->id, $this->search('%'));
        $this->assertNotContains($this->asset->id, $this->search('ZZZ%LT'));
    }

    public function test_search_ignores_an_empty_term(): void
    {
        $this->assertContains($this->asset->id, $this->search('   '));
    }

    public function test_the_asset_index_search_survives_a_sort_join(): void
    {
        // Sorting by category joins the categories table, which also has a
        // `description` column — unqualified columns would be ambiguous there.
        $response = $this->actingAs($this->admin())
            ->get('/assets?search=' . urlencode('Dellzzz Cruzzzz') . '&sort=category&direction=asc');

        $response->assertOk();
    }

    public function test_the_global_search_page_groups_results_across_modules(): void
    {
        $response = $this->actingAs($this->admin())->get('/search?q=Dellzzz');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->where('query', 'Dellzzz')
            ->where('total', fn ($total) => $total > 0)
            ->has('groups', fn ($groups) => $groups->each(fn ($group) => $group
                ->hasAll(['type', 'label', 'items', 'count'])
                ->etc())));

        // The keyword lives on the brand, so both the brand and the asset holding it show up.
        $types = collect($response->viewData('page')['props']['groups'])->pluck('type');
        $this->assertTrue($types->contains('asset'));
        $this->assertTrue($types->contains('brand'));
    }

    public function test_a_user_only_searches_the_modules_they_may_view(): void
    {
        $user = User::create([
            'name'     => 'Limited Testzzz',
            'username' => 'limitedtestzzz',
            'email'    => 'limitedtestzzz@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => false,
        ]);
        $user->permissions()->create(['resource' => 'assets', 'action' => 'view']);

        $response = $this->actingAs($user)->getJson('/search/suggest?q=Dellzzz');

        $response->assertOk();
        $types = collect($response->json('groups'))->pluck('type');

        $this->assertTrue($types->contains('asset'), 'assets.view was granted');
        $this->assertFalse($types->contains('brand'), 'brands.view was not granted');
        $this->assertFalse($types->contains('user'), 'users are admin-only');
    }

    public function test_the_suggest_endpoint_returns_grouped_json(): void
    {
        $response = $this->actingAs($this->admin())->getJson('/search/suggest?q=Dellzzz');

        $response->assertOk();
        $response->assertJsonStructure(['groups' => [['type', 'label', 'items']]]);
    }

    private function admin(): User
    {
        return User::create([
            'name'     => 'Search Testzzz',
            'username' => 'searchtestzzz',
            'email'    => 'searchtestzzz@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);
    }
}
