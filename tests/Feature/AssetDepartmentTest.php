<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The department rule.
 *
 * A department belongs to the asset, not to whoever happens to hold it: it is
 * filled in from the holder once, then stays put when that person resigns,
 * goes inactive or hands the asset back. It exists only at locations flagged
 * as organised into departments.
 */
class AssetDepartmentTest extends TestCase
{
    use DatabaseTransactions;

    private Location $headOffice;
    private Location $warehouse;
    private Department $it;
    private Department $sales;
    private Employee $itStaff;
    private Category $category;
    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headOffice = Location::create(['name' => 'ZZ Head Office', 'has_departments' => true]);
        $this->warehouse  = Location::create(['name' => 'ZZ Warehouse',   'has_departments' => false]);
        $this->it         = Department::create(['code' => 'ZZIT', 'name' => 'ZZ Infotech']);
        $this->sales      = Department::create(['code' => 'ZZSL', 'name' => 'ZZ Sales']);
        $this->category   = Category::create(['name' => 'ZZ Laptop', 'slug' => 'zz-laptop-d', 'prefix' => 'ZZD']);

        $this->itStaff = Employee::create([
            'employee_no' => 'ZZD-001', 'first_name' => 'Dept', 'last_name' => 'Tester',
            'department_id' => $this->it->id, 'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Dept Testzzz', 'username' => 'depttestzzz',
            'email' => 'depttestzzz@example.test', 'password' => bcrypt('secret'), 'is_admin' => true,
        ]);
    }

    private function makeAsset(array $attributes = []): Asset
    {
        return Asset::create(array_merge([
            'asset_tag'           => 'ZZD-' . fake()->unique()->numerify('####'),
            'category_id'         => $this->category->id,
            'current_status'      => 'in_stock',
            'current_location_id' => $this->headOffice->id,
        ], $attributes));
    }

    // ── Rule 2: department exists only where the location says so ──

    public function test_a_department_is_cleared_at_a_location_without_departments(): void
    {
        $asset = $this->makeAsset([
            'current_location_id' => $this->warehouse->id,
            'department_id'       => $this->it->id,
        ]);

        $this->assertNull($asset->fresh()->department_id);
    }

    public function test_a_department_is_kept_at_a_location_with_departments(): void
    {
        $asset = $this->makeAsset(['department_id' => $this->sales->id]);

        $this->assertSame($this->sales->id, $asset->fresh()->department_id);
    }

    public function test_moving_to_a_location_without_departments_clears_the_department(): void
    {
        $asset = $this->makeAsset(['department_id' => $this->sales->id]);

        $asset->update(['current_location_id' => $this->warehouse->id]);

        $this->assertNull($asset->fresh()->department_id);
    }

    // ── Rule 3: filled in from the holder ──

    public function test_issuing_fills_a_blank_department_from_the_holder(): void
    {
        $asset = $this->makeAsset();
        $this->assertNull($asset->department_id);

        $this->actingAs($this->admin())
            ->post("/assets/{$asset->id}/issue", [
                'to_employee_id' => $this->itStaff->id,
                'movement_date'  => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertSame($this->it->id, $asset->fresh()->department_id);
    }

    public function test_issuing_does_not_overwrite_a_department_someone_chose(): void
    {
        $asset = $this->makeAsset(['department_id' => $this->sales->id]);

        $this->actingAs($this->admin())
            ->post("/assets/{$asset->id}/issue", [
                'to_employee_id' => $this->itStaff->id,
                'movement_date'  => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertSame($this->sales->id, $asset->fresh()->department_id, 'an explicit department wins over the holder');
    }

    public function test_issuing_at_a_location_without_departments_leaves_it_empty(): void
    {
        $asset = $this->makeAsset(['current_location_id' => $this->warehouse->id]);

        $this->actingAs($this->admin())
            ->post("/assets/{$asset->id}/issue", [
                'to_employee_id' => $this->itStaff->id,
                'movement_date'  => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertNull($asset->fresh()->department_id);
    }

    // ── Rule 4: it does not move with the person ──

    public function test_returning_the_asset_keeps_the_department(): void
    {
        $asset = $this->makeAsset(['current_holder_id' => $this->itStaff->id, 'current_status' => 'assigned']);
        $asset->refresh();
        $this->assertSame($this->it->id, $asset->department_id);

        $this->actingAs($this->admin())
            ->post("/assets/{$asset->id}/return", [
                'movement_date' => now()->format('Y-m-d'),
                'new_status'    => 'in_stock',
            ])
            ->assertRedirect();

        $asset->refresh();
        $this->assertNull($asset->current_holder_id, 'the holder is cleared');
        $this->assertSame($this->it->id, $asset->department_id, 'the department stays');
    }

    public function test_the_holder_resigning_does_not_change_the_department(): void
    {
        $asset = $this->makeAsset(['current_holder_id' => $this->itStaff->id, 'current_status' => 'assigned']);
        $asset->refresh();

        $this->itStaff->update(['status' => 'resigned', 'date_resigned' => now()->format('Y-m-d')]);

        $this->assertSame($this->it->id, $asset->fresh()->department_id);
    }

    public function test_the_holder_moving_department_does_not_change_the_asset(): void
    {
        $asset = $this->makeAsset(['current_holder_id' => $this->itStaff->id, 'current_status' => 'assigned']);
        $asset->refresh();

        $this->itStaff->update(['department_id' => $this->sales->id]);

        $this->assertSame($this->it->id, $asset->fresh()->department_id, 'the asset keeps the department it was issued under');
    }

    public function test_an_explicit_edit_changes_the_department(): void
    {
        $asset = $this->makeAsset(['department_id' => $this->it->id]);

        $asset->update(['department_id' => $this->sales->id]);

        $this->assertSame($this->sales->id, $asset->fresh()->department_id);
    }

    // ── Rule 1 & 2: the form ──

    public function test_the_form_requires_a_department_at_a_location_with_departments(): void
    {
        $this->actingAs($this->admin())
            ->post('/assets', [
                'asset_tag'               => 'ZZD-FORM-1',
                'expected_lifespan_years' => 5,
                'current_status'          => 'in_stock',
                'current_location_id'     => $this->headOffice->id,
            ])
            ->assertSessionHasErrors('department_id');
    }

    public function test_the_form_does_not_require_a_department_elsewhere(): void
    {
        $this->actingAs($this->admin())
            ->post('/assets', [
                'asset_tag'               => 'ZZD-FORM-2',
                'expected_lifespan_years' => 5,
                'current_status'          => 'in_stock',
                'current_location_id'     => $this->warehouse->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Asset::where('asset_tag', 'ZZD-FORM-2')->value('department_id'));
    }

    public function test_the_form_offers_the_lookups_the_department_field_needs(): void
    {
        $lookups = $this->actingAs($this->admin())->get('/assets/create')
            ->assertOk()
            ->viewData('page')['props']['lookups'];

        $this->assertArrayHasKey('departments', $lookups);
        $this->assertArrayHasKey('has_departments', (array) $lookups['locations'][0]);
        $this->assertArrayHasKey('department_id', (array) $lookups['employees'][0]);
    }

    // ── Rule 5: the chart ──

    public function test_the_chart_groups_by_department_at_head_office_and_by_location_elsewhere(): void
    {
        $this->makeAsset(['department_id' => $this->it->id]);
        $this->makeAsset(['department_id' => $this->it->id]);
        $this->makeAsset(['department_id' => $this->sales->id]);
        $this->makeAsset(['current_location_id' => $this->warehouse->id]);

        $bars = collect($this->actingAs($this->admin())->get('/')->assertOk()
            ->viewData('page')['props']['charts']['by_department']);

        $named = $bars->keyBy('name');

        $this->assertSame(2, $named[$this->it->name]['count']);
        $this->assertSame(1, $named[$this->sales->name]['count']);
        $this->assertSame(1, $named[$this->warehouse->name]['count'], 'a warehouse reports under its own name');

        $this->assertFalse($bars->contains(fn ($b) => $b['name'] === '(no department)'));
    }

    public function test_every_chart_bar_links_to_a_list_with_the_same_count(): void
    {
        $this->makeAsset(['department_id' => $this->it->id]);
        $this->makeAsset(['department_id' => $this->sales->id]);
        $this->makeAsset(['current_location_id' => $this->warehouse->id]);
        $this->makeAsset(['current_location_id' => $this->warehouse->id]);

        $bars = $this->actingAs($this->admin())->get('/')->assertOk()
            ->viewData('page')['props']['charts']['by_department'];

        $this->assertNotEmpty($bars);

        foreach ($bars as $bar) {
            $this->assertNotEmpty($bar['filter'], "bar \"{$bar['name']}\" must carry a filter");

            $total = $this->actingAs($this->admin())
                ->get('/assets?' . http_build_query($bar['filter']))
                ->assertOk()
                ->viewData('page')['props']['assets']['total'];

            $this->assertSame((int) $bar['count'], (int) $total, "bar \"{$bar['name']}\" does not match its list");
        }
    }

    public function test_an_asset_with_no_location_still_appears_on_the_chart(): void
    {
        // Otherwise the bars would quietly stop adding up to the total.
        $this->makeAsset(['current_location_id' => null]);

        $bars = collect($this->actingAs($this->admin())->get('/')->assertOk()
            ->viewData('page')['props']['charts']['by_department']);

        $unassigned = $bars->firstWhere('name', 'Unassigned');
        $this->assertNotNull($unassigned, 'an asset with no location must still be counted somewhere');
        $this->assertGreaterThanOrEqual(1, $unassigned['count']);
    }

    public function test_the_chart_accounts_for_every_asset(): void
    {
        $this->makeAsset(['department_id' => $this->it->id]);
        $this->makeAsset(['current_location_id' => $this->warehouse->id]);
        $this->makeAsset(['current_location_id' => null]);

        $props = $this->actingAs($this->admin())->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame(
            (int) $props['stats']['total_assets'],
            collect($props['charts']['by_department'])->sum('count'),
            'the bars must add up to the total asset count',
        );
    }

    // ── Rule 6: backfill ──

    public function test_the_backfill_rule_matches_what_the_migration_did(): void
    {
        // Same shape as the migration: a head-office asset with a holder but
        // no department ends up on the holder's department.
        $asset = $this->makeAsset(['current_holder_id' => $this->itStaff->id]);

        $this->assertSame($this->it->id, $asset->fresh()->department_id);
    }

    public function test_locations_can_be_flagged_as_organised_into_departments(): void
    {
        $this->actingAs($this->admin())
            ->post('/locations', ['name' => 'ZZ Branch Office', 'has_departments' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) Location::where('name', 'ZZ Branch Office')->value('has_departments'));
    }
}
