<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name'  => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->seedBrands();
        $this->seedCategories();
        $this->seedDepartments();
        $this->seedLocations();
        $this->seedEmployees();
        $this->seedAssets();
    }

    private function seedBrands(): void
    {
        $brands = ['Dell', 'HP', 'Lenovo', 'Apple', 'Asus', 'Acer', 'Samsung', 'Logitech', 'Epson', 'Canon'];
        foreach ($brands as $name) {
            Brand::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'description' => "$name brand products", 'is_active' => true]
            );
        }
    }

    private function seedCategories(): void
    {
        $cats = [
            ['name' => 'Laptop',    'prefix' => 'LPT', 'description' => 'Portable computers'],
            ['name' => 'Desktop',   'prefix' => 'DSK', 'description' => 'Workstation desktops'],
            ['name' => 'Monitor',   'prefix' => 'MON', 'description' => 'Display monitors'],
            ['name' => 'Printer',   'prefix' => 'PRN', 'description' => 'Printers and MFPs'],
            ['name' => 'Keyboard',  'prefix' => 'KBD', 'description' => 'Input keyboards'],
            ['name' => 'Mouse',     'prefix' => 'MSE', 'description' => 'Pointing devices'],
            ['name' => 'Headset',   'prefix' => 'HST', 'description' => 'Audio headsets'],
            ['name' => 'Webcam',    'prefix' => 'WBC', 'description' => 'Cameras'],
            ['name' => 'Router',    'prefix' => 'RTR', 'description' => 'Network routers'],
            ['name' => 'Server',    'prefix' => 'SRV', 'description' => 'Rack servers'],
        ];
        foreach ($cats as $c) {
            Category::firstOrCreate(
                ['name' => $c['name']],
                ['slug' => Str::slug($c['name']), 'prefix' => $c['prefix'], 'description' => $c['description'], 'is_active' => true]
            );
        }
    }

    private function seedDepartments(): void
    {
        $depts = [
            ['code' => 'IT',  'name' => 'Information Technology'],
            ['code' => 'HR',  'name' => 'Human Resources'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'OPS', 'name' => 'Operations'],
            ['code' => 'SLS', 'name' => 'Sales'],
            ['code' => 'MKT', 'name' => 'Marketing'],
            ['code' => 'ADM', 'name' => 'Administration'],
            ['code' => 'LOG', 'name' => 'Logistics'],
        ];
        foreach ($depts as $d) {
            Department::firstOrCreate(['code' => $d['code']], $d + ['is_active' => true]);
        }
    }

    private function seedLocations(): void
    {
        $locs = [
            ['name' => 'Main Office - 5F',     'description' => '5th floor, IT and Admin areas'],
            ['name' => 'Main Office - 4F',     'description' => '4th floor, HR and Finance'],
            ['name' => 'Main Office - 3F',     'description' => '3rd floor, Sales and Marketing'],
            ['name' => 'Warehouse',            'description' => 'Storage facility for new and decommissioned assets'],
            ['name' => 'Server Room',          'description' => 'Climate-controlled server and network room'],
            ['name' => 'Branch - Cebu',        'description' => 'Cebu satellite office'],
            ['name' => 'Branch - Davao',       'description' => 'Davao satellite office'],
            ['name' => 'Remote / Home Office', 'description' => 'Assigned to remote workers'],
        ];
        foreach ($locs as $l) {
            Location::firstOrCreate(['name' => $l['name']], $l + ['is_active' => true]);
        }
    }

    private function seedEmployees(): void
    {
        if (Employee::count() > 0) return;

        $firstNames = ['Maria', 'Juan', 'Jose', 'Ana', 'Pedro', 'Carmen', 'Luis', 'Rosa', 'Antonio', 'Sofia',
                       'Miguel', 'Isabela', 'Carlos', 'Elena', 'Diego', 'Lucia', 'Fernando', 'Patricia', 'Ricardo', 'Beatriz'];
        $lastNames  = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Aquino',
                       'Castillo', 'Villanueva', 'Pascual', 'Rivera', 'Hernandez', 'Lopez', 'Morales', 'Fernandez', 'Ortega', 'Navarro'];
        $positions  = ['Manager', 'Senior Analyst', 'Analyst', 'Associate', 'Specialist', 'Coordinator', 'Officer', 'Supervisor', 'Lead', 'Director'];

        $deptIds = Department::pluck('id')->all();
        $locIds  = Location::pluck('id')->all();
        $statuses = ['active', 'active', 'active', 'active', 'active', 'inactive', 'resigned'];

        for ($i = 1; $i <= 45; $i++) {
            $first = $firstNames[array_rand($firstNames)];
            $last  = $lastNames[array_rand($lastNames)];
            Employee::create([
                'employee_no'   => 'EMP-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'first_name'    => $first,
                'last_name'     => $last,
                'email'         => strtolower("$first.$last." . $i) . '@example.com',
                'contact_no'    => '+63 9' . random_int(100000000, 999999999),
                'position'      => $positions[array_rand($positions)],
                'department_id' => $deptIds[array_rand($deptIds)],
                'location_id'   => $locIds[array_rand($locIds)],
                'date_hired'    => Carbon::now()->subDays(random_int(30, 1500))->format('Y-m-d'),
                'status'        => $statuses[array_rand($statuses)],
            ]);
        }
    }

    private function seedAssets(): void
    {
        if (Asset::count() > 0) return;

        $brandIds = Brand::pluck('id')->all();
        $categories = Category::all();
        $locIds = Location::pluck('id')->all();
        $goodConditionId = \App\Models\Condition::where('slug', 'good')->value('id');

        $counters = [];
        foreach ($categories as $cat) {
            $counters[$cat->id] = 1;

            // 8-15 of each category
            $count = random_int(8, 15);
            for ($i = 0; $i < $count; $i++) {
                $tag = "{$cat->prefix}-" . str_pad($counters[$cat->id]++, 3, '0', STR_PAD_LEFT);
                $purchaseDate = Carbon::now()->subMonths(random_int(1, 72));
                $cost = match (true) {
                    $cat->prefix === 'LPT' => random_int(30000, 90000),
                    $cat->prefix === 'DSK' => random_int(20000, 60000),
                    $cat->prefix === 'MON' => random_int(8000, 25000),
                    $cat->prefix === 'PRN' => random_int(10000, 35000),
                    $cat->prefix === 'SRV' => random_int(150000, 400000),
                    $cat->prefix === 'RTR' => random_int(5000, 30000),
                    default => random_int(500, 5000),
                };

                Asset::create([
                    'asset_tag'               => $tag,
                    'brand_id'                => $brandIds[array_rand($brandIds)],
                    'category_id'             => $cat->id,
                    'model'                   => 'Model-' . random_int(1000, 9999),
                    'serial_number'           => strtoupper(Str::random(10)),
                    'purchase_date'           => $purchaseDate->format('Y-m-d'),
                    'purchase_cost'           => $cost,
                    'expected_lifespan_years' => 5,
                    'warranty_until'          => $purchaseDate->copy()->addYears(5)->format('Y-m-d'),
                    'condition_id'            => $goodConditionId,
                    'current_status'          => 'in_stock',
                    'current_location_id'     => $locIds[array_rand($locIds)],
                ]);
            }
        }
    }
}
