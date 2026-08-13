<?php

namespace Database\Seeders;

use App\Models\Signatory;
use Illuminate\Database\Seeder;

class SignatorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Checked and Issued By
            ['name' => 'JOSEPH CARRIDO',             'title' => 'Junior IT Associate', 'role' => 'checked_by', 'sort_order' => 1],
            ['name' => 'CYRENN DANN LEAN MENDOZA',   'title' => 'Junior IT Associate', 'role' => 'checked_by', 'sort_order' => 2],
            ['name' => 'JOSHUA SALCEDO',             'title' => 'Junior IT Associate', 'role' => 'checked_by', 'sort_order' => 3],
            // Reviewed By
            ['name' => 'DINN JOSEPH WILLIAM COLICO', 'title' => 'Technical Head',      'role' => 'reviewed_by', 'sort_order' => 1],
            // Approved By
            ['name' => 'NORMAN GUIRIBA',             'title' => 'MIS Head',            'role' => 'approved_by', 'sort_order' => 1],
        ];

        foreach ($rows as $r) {
            Signatory::firstOrCreate(
                ['name' => $r['name'], 'role' => $r['role']],
                $r + ['is_active' => true],
            );
        }
    }
}
