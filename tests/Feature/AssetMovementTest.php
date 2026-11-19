<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * What counts as a transfer.
 *
 * A transfer is a handover: the asset leaves one person and reaches another.
 * Handing out an asset nobody held is not a handover, it is the first
 * issuance — and recording it as a transfer leaves a row with no source, which
 * the employee timeline can only render as "Transferred … From — → Someone".
 */
class AssetMovementTest extends TestCase
{
    use DatabaseTransactions;

    private Location $office;
    private Category $category;
    private Employee $alice;
    private Employee $bob;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $department = Department::create(['code' => 'ZZMV', 'name' => 'ZZ Movements']);
        $this->office   = Location::create(['name' => 'ZZ Movement Office', 'has_departments' => false]);
        $this->category = Category::create(['name' => 'ZZ Mover', 'slug' => 'zz-mover', 'prefix' => 'ZZM']);

        $this->alice = Employee::create([
            'employee_no' => 'ZZM-001', 'first_name' => 'Alice', 'last_name' => 'Mover',
            'department_id' => $department->id, 'status' => 'active',
        ]);
        $this->bob = Employee::create([
            'employee_no' => 'ZZM-002', 'first_name' => 'Bob', 'last_name' => 'Mover',
            'department_id' => $department->id, 'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Movement Testzzz', 'username' => 'movementtestzzz',
            'email' => 'movementtestzzz@example.test',
            'password' => bcrypt('secret'), 'is_admin' => true,
        ]);
    }

    private function makeAsset(array $attributes = []): Asset
    {
        return Asset::create(array_merge([
            'asset_tag'           => 'ZZM-' . fake()->unique()->numerify('####'),
            'category_id'         => $this->category->id,
            'current_status'      => 'in_stock',
            'current_location_id' => $this->office->id,
        ], $attributes));
    }

    public function test_transferring_an_unheld_asset_is_recorded_as_an_issuance(): void
    {
        $asset = $this->makeAsset();

        $this->actingAs($this->admin)
            ->post("/assets/{$asset->id}/transfer", [
                'to_employee_id' => $this->alice->id,
                'movement_date'  => '2026-01-15',
            ])
            ->assertSessionHasNoErrors();

        $movement = AssetMovement::where('asset_id', $asset->id)->latest('id')->first();

        $this->assertNotNull($movement);
        $this->assertSame('issuance', $movement->type,
            'An asset coming out of stock was handed out, not handed over.');
        $this->assertNull($movement->from_employee_id);
        $this->assertSame($this->alice->id, $movement->to_employee_id);

        // The asset still ends up where it should.
        $this->assertSame($this->alice->id, $asset->fresh()->current_holder_id);
        $this->assertSame('assigned', $asset->fresh()->current_status);
    }

    public function test_transferring_a_held_asset_is_still_a_transfer(): void
    {
        $asset = $this->makeAsset([
            'current_holder_id' => $this->alice->id,
            'current_status'    => 'assigned',
        ]);

        $this->actingAs($this->admin)
            ->post("/assets/{$asset->id}/transfer", [
                'to_employee_id' => $this->bob->id,
                'movement_date'  => '2026-01-16',
            ])
            ->assertSessionHasNoErrors();

        $movement = AssetMovement::where('asset_id', $asset->id)->latest('id')->first();

        $this->assertSame('transfer', $movement->type);
        $this->assertSame($this->alice->id, $movement->from_employee_id);
        $this->assertSame($this->bob->id, $movement->to_employee_id);
    }

    public function test_a_location_only_move_is_still_a_transfer(): void
    {
        $elsewhere = Location::create(['name' => 'ZZ Elsewhere', 'has_departments' => false]);
        $asset     = $this->makeAsset();

        $this->actingAs($this->admin)
            ->post("/assets/{$asset->id}/transfer", [
                'to_location_id' => $elsewhere->id,
                'movement_date'  => '2026-01-17',
            ])
            ->assertSessionHasNoErrors();

        $movement = AssetMovement::where('asset_id', $asset->id)->latest('id')->first();

        $this->assertSame('transfer', $movement->type,
            'Moving stock between locations issues it to nobody.');
        $this->assertNull($movement->to_employee_id);
        $this->assertSame($elsewhere->id, $movement->to_location_id);
    }
}
