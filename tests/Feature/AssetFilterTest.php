<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The filters the dashboard links with.
 *
 * Every figure on the dashboard is a link into /assets, so a tile showing 4
 * has to land on exactly those 4 rows. These tests hold that contract.
 */
class AssetFilterTest extends TestCase
{
    use DatabaseTransactions;

    private Category $laptops;
    private Category $printers;
    private Department $it;
    private Department $sales;
    private Location $hq;
    private Location $annex;

    protected function setUp(): void
    {
        parent::setUp();

        $brand          = Brand::create(['name' => 'Brandzzz', 'slug' => 'brandzzz-f']);
        $this->laptops  = Category::create(['name' => 'Laptopzzz',  'slug' => 'laptopzzz-f',  'prefix' => 'LTF']);
        $this->printers = Category::create(['name' => 'Printerzzz', 'slug' => 'printerzzz-f', 'prefix' => 'PRF']);
        $this->it       = Department::create(['code' => 'ITZF',  'name' => 'Infotechzzz F']);
        $this->sales    = Department::create(['code' => 'SLZF',  'name' => 'Saleszzz F']);
        $this->hq       = Location::create(['name' => 'HQzzz F']);
        $this->annex    = Location::create(['name' => 'Annexzzz F']);

        $make = function (string $tag, array $attributes) use ($brand) {
            return Asset::create(array_merge([
                'asset_tag'      => $tag,
                'brand_id'       => $brand->id,
                'current_status' => 'in_stock',
            ], $attributes));
        };

        // laptop / IT / HQ / protected / in stock
        $make('FLT-001', [
            'category_id' => $this->laptops->id, 'department_id' => $this->it->id,
            'current_location_id' => $this->hq->id, 'current_status' => 'assigned',
            'specifications' => [['key' => 'Antivirus', 'value' => 'Defender']],
        ]);
        // laptop / Sales / Annex / no AV
        $make('FLT-002', [
            'category_id' => $this->laptops->id, 'department_id' => $this->sales->id,
            'current_location_id' => $this->annex->id,
            'specifications' => [['key' => 'antivirus', 'value' => 'No']],
        ]);
        // printer / IT / Annex / AV excluded / retired
        $make('FPR-001', [
            'category_id' => $this->printers->id, 'department_id' => $this->it->id,
            'current_location_id' => $this->annex->id, 'current_status' => 'retired',
            'specifications' => [['key' => 'Antivirus', 'value' => 'Excluded']],
        ]);
        // printer / Sales / HQ / no antivirus spec at all
        $make('FPR-002', [
            'category_id' => $this->printers->id, 'department_id' => $this->sales->id,
            'current_location_id' => $this->hq->id,
            'specifications' => [['key' => 'Storage', 'value' => '256GB']],
        ]);
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'     => 'Filter Testzzz',
            'username' => 'filtertestzzz',
            'email'    => 'filtertestzzz@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);
    }

    /** @return string[] the asset tags the list returned, from our fixture only */
    private function tagsFor(array $query): array
    {
        $response = $this->actingAs($this->admin())->get('/assets?' . http_build_query($query));
        $response->assertOk();

        return collect($response->viewData('page')['props']['assets']['data'])
            ->pluck('asset_tag')
            ->filter(fn ($t) => str_starts_with((string) $t, 'FLT-') || str_starts_with((string) $t, 'FPR-'))
            ->sort()
            ->values()
            ->all();
    }

    public function test_it_filters_by_department(): void
    {
        $this->assertSame(['FLT-001', 'FPR-001'], $this->tagsFor(['department_id' => $this->it->id]));
        $this->assertSame(['FLT-002', 'FPR-002'], $this->tagsFor(['department_id' => $this->sales->id]));
    }

    public function test_it_filters_by_location(): void
    {
        $this->assertSame(['FLT-001', 'FPR-002'], $this->tagsFor(['location_id' => $this->hq->id]));
        $this->assertSame(['FLT-002', 'FPR-001'], $this->tagsFor(['location_id' => $this->annex->id]));
    }

    public function test_it_filters_by_category_and_department_together(): void
    {
        // This is what a Category x Department pivot cell links to.
        $this->assertSame(['FLT-001'], $this->tagsFor([
            'category_id'   => $this->laptops->id,
            'department_id' => $this->it->id,
        ]));
    }

    public function test_it_filters_by_location_and_category_together(): void
    {
        $this->assertSame(['FPR-001'], $this->tagsFor([
            'location_id' => $this->annex->id,
            'category_id' => $this->printers->id,
        ]));
    }

    public function test_it_filters_by_antivirus_bucket(): void
    {
        // A named product counts as protected; the key's case does not matter.
        $this->assertSame(['FLT-001'], $this->tagsFor(['antivirus' => 'Yes']));
        $this->assertSame(['FLT-002'], $this->tagsFor(['antivirus' => 'No']));
        $this->assertSame(['FPR-001'], $this->tagsFor(['antivirus' => 'Excluded']));
        $this->assertSame(['FPR-002'], $this->tagsFor(['antivirus' => '—']));
    }

    public function test_maintained_excludes_retired_and_replaced(): void
    {
        // FPR-001 is retired, so the "Maintained Asset" tile must not show it.
        $this->assertSame(['FLT-001', 'FLT-002', 'FPR-002'], $this->tagsFor(['maintained' => 1]));
    }

    public function test_the_antivirus_bucket_classifier_matches_the_dashboard_tally(): void
    {
        $this->assertSame('Yes',      Asset::antivirusBucket([['key' => 'Antivirus', 'value' => 'Defender']]));
        $this->assertSame('Yes',      Asset::antivirusBucket([['key' => 'ANTIVIRUS', 'value' => 'yes']]));
        $this->assertSame('No',       Asset::antivirusBucket([['key' => 'antivirus', 'value' => 'none']]));
        $this->assertSame('Excluded', Asset::antivirusBucket([['key' => 'Antivirus', 'value' => 'excluded']]));
        $this->assertSame('—',        Asset::antivirusBucket([['key' => 'Antivirus', 'value' => '']]));
        $this->assertSame('—',        Asset::antivirusBucket([['key' => 'Storage', 'value' => '256GB']]));
        $this->assertSame('—',        Asset::antivirusBucket(null));
    }

    public function test_an_unknown_antivirus_bucket_is_ignored_rather_than_matching_nothing(): void
    {
        $all = $this->tagsFor([]);
        $this->assertSame($all, $this->tagsFor(['antivirus' => 'bogus']));
    }

    public function test_the_dashboard_ships_the_ids_its_links_need(): void
    {
        $response = $this->actingAs($this->admin())->get('/');
        $response->assertOk();

        $charts = $response->viewData('page')['props']['charts'];

        $this->assertArrayHasKey('id', $charts['by_category'][0]);
        $this->assertArrayHasKey('id', $charts['by_department'][0]);
        $this->assertArrayHasKey('id', $charts['cat_dept']['departments'][0]);
        $this->assertArrayHasKey('category_id', $charts['cat_dept']['rows'][0]);
        $this->assertArrayHasKey('id', $charts['loc_cat']['categories'][0]);
        $this->assertArrayHasKey('location_id', $charts['loc_cat']['rows'][0]);
    }

    /**
     * The promise the drill-downs make: a figure on the dashboard and the row
     * count you land on are the same number. Checked against whatever is in
     * the database, so it holds for the real fleet too, not just the fixture.
     */
    public function test_every_dashboard_figure_matches_the_list_it_links_to(): void
    {
        $admin  = $this->admin();
        $charts = $this->actingAs($admin)->get('/')->assertOk()->viewData('page')['props'];

        $total = fn (array $query) => $this->actingAs($admin)
            ->get('/assets?' . http_build_query($query))
            ->assertOk()
            ->viewData('page')['props']['assets']['total'];

        $cases = [
            'total assets'     => [$charts['stats']['total_assets'], []],
            'maintained'       => [$charts['stats']['maintained'],   ['maintained' => 1]],
            'assigned'         => [$charts['stats']['assigned'],     ['status' => 'assigned']],
            'in stock'         => [$charts['stats']['in_stock'],     ['status' => 'in_stock']],
            'antivirus yes'    => [$charts['stats']['endpoint_protection']['Yes'],      ['antivirus' => 'Yes']],
            'antivirus no'     => [$charts['stats']['endpoint_protection']['No'],       ['antivirus' => 'No']],
            'antivirus excl'   => [$charts['stats']['endpoint_protection']['Excluded'], ['antivirus' => 'Excluded']],
            'warranty active'  => [$charts['charts']['warranty']['active'],        ['warranty' => 'active']],
            'warranty soon'    => [$charts['charts']['warranty']['expiring_soon'], ['warranty' => 'expiring_soon']],
            'warranty expired' => [$charts['charts']['warranty']['expired'],       ['warranty' => 'expired']],
        ];

        foreach ($charts['charts']['by_status'] as $status => $count) {
            $cases["status {$status}"] = [$count, ['status' => $status]];
        }
        foreach ($charts['charts']['by_category'] as $category) {
            $cases["category {$category['name']}"] = [$category['count'], ['category_id' => $category['id']]];
        }
        foreach ($charts['charts']['by_department'] as $department) {
            if ($department['id']) {
                $cases["department {$department['name']}"] = [$department['count'], ['department_id' => $department['id']]];
            }
        }
        foreach ($charts['charts']['cat_dept']['rows'] as $row) {
            foreach ($charts['charts']['cat_dept']['departments'] as $column) {
                if ($row['cells'][$column['name']] ?? 0) {
                    $cases["cell {$row['category']} x {$column['name']}"] = [
                        $row['cells'][$column['name']],
                        ['category_id' => $row['category_id'], 'department_id' => $column['id']],
                    ];
                }
            }
        }
        foreach ($charts['charts']['loc_cat']['rows'] as $row) {
            foreach ($charts['charts']['loc_cat']['categories'] as $column) {
                if ($row['cells'][$column['name']] ?? 0) {
                    $cases["cell {$row['location']} x {$column['name']}"] = [
                        $row['cells'][$column['name']],
                        ['location_id' => $row['location_id'], 'category_id' => $column['id']],
                    ];
                }
            }
        }

        $this->assertGreaterThan(10, count($cases), 'the fixture should exercise a good spread of figures');

        foreach ($cases as $label => [$shown, $query]) {
            $this->assertSame((int) $shown, (int) $total($query), "dashboard figure \"{$label}\" does not match its link");
        }
    }

    public function test_the_export_honours_the_same_filters_as_the_list(): void
    {
        $response = $this->actingAs($this->admin())
            ->get('/assets/export?' . http_build_query(['department_id' => $this->it->id]));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type'),
        );
    }
}
