<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Signatory;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates an Accountability Agreement Form (.docx) by filling values into a Word template
 * (resources/templates/accountability.docx). The template controls the entire visual design;
 * this service just injects the dynamic data.
 *
 * To adjust layout / fonts / borders → edit the template directly in Word. Placeholder tags
 * `${xxx}` inside the template are what map to `setValue()` calls here.
 */
class AccountabilityDocxGenerator
{
    private const TEMPLATE_PATH = 'resources/templates/accountability.docx';

    /**
     * @param  int[]|null  $assetIds  when given, only these assets are included; otherwise all held assets.
     */
    public function generate(Employee $employee, ?array $assetIds = null): StreamedResponse
    {
        $employee->load(['department']);
        $query = $employee->heldAssets()
            ->with(['brand:id,name', 'category:id,name'])
            ->orderBy('asset_tag');
        if ($assetIds !== null && count($assetIds) > 0) {
            $query->whereIn('assets.id', $assetIds);
        }
        $assets = $query->get();

        $tpl = new TemplateProcessor(base_path(self::TEMPLATE_PATH));

        $this->fillHeaderMeta($tpl, $employee, $assets);
        $this->fillEquipmentTable($tpl, $assets);
        $this->fillUnitCost($tpl, $assets);
        $this->fillSignatories($tpl, $employee);

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $employee->full_name);
        $filename = date('Y-m-d') . "_ACCOUNTABILITY_{$safeName}.docx";

        return response()->streamDownload(function () use ($tpl) {
            $tpl->saveAs('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    // ─── Section I: header meta ───
    private function fillHeaderMeta(TemplateProcessor $tpl, Employee $employee, $assets): void
    {
        $controlNo = $assets->last()?->asset_tag ?: $employee->employee_no;
        $tpl->setValue('control_no',    $this->s($controlNo));
        $tpl->setValue('employee_name', $this->s(strtoupper($employee->full_name)));
        $tpl->setValue('employee_id',   $this->s($employee->employee_no));
        $tpl->setValue('position',      $this->s(strtoupper((string) $employee->position)));
        $tpl->setValue('department',    $this->s(strtoupper((string) $employee->department?->name)));
    }

    // ─── Section II: equipment table (cloneRow per asset) ───
    private function fillEquipmentTable(TemplateProcessor $tpl, $assets): void
    {
        if ($assets->isEmpty()) {
            $tpl->setValue('qty',           '');
            $tpl->setValue('description',   '');
            $tpl->setValue('serial_number', '');
            $tpl->setValue('asset_code',    '');
            return;
        }
        $tpl->cloneRow('qty', $assets->count());
        foreach ($assets->values() as $i => $a) {
            $n = $i + 1;
            $tpl->setValue("qty#{$n}",           '1');
            $tpl->setValue("description#{$n}",   $this->s($this->composeDescription($a)));
            $tpl->setValue("serial_number#{$n}", $this->s($a->serial_number ?: ''));
            $tpl->setValue("asset_code#{$n}",    $this->s($a->asset_tag));
        }
    }

    // ─── Section III: unit cost (one block per asset via cloneBlock) ───
    private function fillUnitCost(TemplateProcessor $tpl, $assets): void
    {
        if ($assets->isEmpty()) {
            $tpl->cloneBlock('block_unit', 0, true, false);
            $tpl->setValue('total', 'PHP 0.00');
            return;
        }

        // cloneBlock duplicates the block N times and suffixes vars with #1, #2, ...
        $tpl->cloneBlock('block_unit', $assets->count(), true, true);

        foreach ($assets->values() as $i => $a) {
            $n = $i + 1;
            $lifespan = (int) ($a->expected_lifespan_years ?: 5);
            $tpl->setValue("unit_type#{$n}",      $this->s('COMPUTER ' . strtoupper($a->category?->name ?: 'DEVICE') . ' UNIT'));
            $tpl->setValue("vendor#{$n}",         $this->s(strtoupper((string) ($a->vendor ?: ''))));
            $tpl->setValue("purchased_date#{$n}", $this->s($a->purchase_date?->format('n/d/Y') ?: ''));
            $tpl->setValue("deployed_date#{$n}",  $this->s($a->deployment_date?->format('n/d/Y') ?: ''));
            $tpl->setValue("life_span#{$n}",      $this->s($this->numberWord($lifespan) . " ({$lifespan}) YEARS"));
        }

        $total = $assets->sum(fn ($a) => (float) ($a->purchase_cost ?? 0));
        $tpl->setValue('total', 'PHP ' . number_format($total, 2));
    }

    // ─── Section VII: signatories from master data ───
    private function fillSignatories(TemplateProcessor $tpl, Employee $employee): void
    {
        $sigs = Signatory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->groupBy('role');

        // Checked and Issued By — up to 3 slots
        $checked = $sigs->get('checked_by') ?? collect();
        for ($i = 1; $i <= 3; $i++) {
            $p = $checked->get($i - 1);
            $tpl->setValue("checked_{$i}_name",  $this->s($p ? strtoupper($p->name) : ''));
            $tpl->setValue("checked_{$i}_title", $this->s($p?->title ?: ''));
        }

        // Reviewed By — 1 slot
        $reviewed = $sigs->get('reviewed_by')?->first();
        $tpl->setValue('reviewed_name',  $this->s($reviewed ? strtoupper($reviewed->name) : ''));
        $tpl->setValue('reviewed_title', $this->s($reviewed?->title ?: ''));

        // Approved By — 1 slot
        $approved = $sigs->get('approved_by')?->first();
        $tpl->setValue('approved_name',  $this->s($approved ? strtoupper($approved->name) : ''));
        $tpl->setValue('approved_title', $this->s($approved?->title ?: ''));

        // Conforme = the assignee
        $tpl->setValue('conforme_name', $this->s(strtoupper($employee->full_name)));

        // Dates: Checked & Reviewed = today; Approved & Conforme = blank
        $today = date('n/d/Y');
        $tpl->setValue('date_checked',   $today);
        $tpl->setValue('date_reviewed',  $today);
        $tpl->setValue('date_approved',  '');
        $tpl->setValue('date_conforme',  '');
        $tpl->setValue('date_returned',  '');
        $tpl->setValue('date_received',  '');
    }

    private function s(?string $value): string
    {
        return (string) $value;
    }

    private function composeDescription($asset): string
    {
        $type = strtoupper($asset->category?->name ?: $asset->description ?: 'DEVICE');

        $peripheralTypes = ['MONITOR', 'PRINTER', 'KEYBOARD', 'MOUSE', 'SCANNER', 'ROUTER', 'SWITCH', 'UPS'];
        if (in_array($type, $peripheralTypes, true)) {
            $bits = array_filter([$asset->brand?->name, $asset->model], fn ($v) => (bool) $v);
            $bits = array_map('strtoupper', $bits);
            return $bits ? "{$type} - " . implode(' ', $bits) : $type;
        }

        // Computers → specs from the asset's Specifications JSON
        $specs = collect($asset->specifications ?? [])->mapWithKeys(function ($s) {
            if (is_array($s)) return [strtolower($s['key'] ?? '') => (string) ($s['value'] ?? '')];
            return [];
        })->filter();

        $order = ['ram', 'processor', 'os name', 'storage'];
        $bits = [];
        foreach ($order as $k) {
            if ($v = $specs->get($k)) $bits[] = strtoupper($v);
        }
        return $bits ? "{$type} - " . implode(' | ', $bits) : $type;
    }

    private function numberWord(int $n): string
    {
        $words = [1 => 'ONE', 2 => 'TWO', 3 => 'THREE', 4 => 'FOUR', 5 => 'FIVE',
                  6 => 'SIX', 7 => 'SEVEN', 8 => 'EIGHT', 9 => 'NINE', 10 => 'TEN'];
        return $words[$n] ?? (string) $n;
    }
}
