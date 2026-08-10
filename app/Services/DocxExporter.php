<?php

namespace App\Services;

use App\Models\AssetPermit;
use App\Models\IncidentReport;
use App\Models\Recommendation;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocxExporter
{
    private const FONT_FAMILY = 'Calibri';

    // 8.5in × 11in US Letter
    private const PAGE_W = 12240; // twips (8.5in)
    private const PAGE_H = 15840; // twips (11in)
    private const MARGIN_X = 720; // twips (0.5in)
    private const MARGIN_Y = 720;

    public function permit(AssetPermit $permit): StreamedResponse
    {
        $permit->load([
            'employee.department',
            'requestedBy', 'issuedBy', 'notedBy', 'notedBySecondary', 'approvedBy',
            'items.asset',
        ]);

        $word = $this->newDocument();
        $section = $word->addSection($this->pageSetup());

        // ── Header: logo + company ──
        $this->addCompanyHeader($section);

        // ── Title bar ──
        $titleTable = $section->addTable([
            'borderTopSize' => 12, 'borderBottomSize' => 12,
            'borderTopColor' => '000000', 'borderBottomColor' => '000000',
            'cellMargin' => 80, 'width' => 100, 'unit' => 'pct',
        ]);
        $titleTable->addRow();
        $titleTable->addCell(self::PAGE_W - self::MARGIN_X * 2, ['valign' => 'center'])
            ->addText('PERMIT TO BRING ASSET',
                ['name' => self::FONT_FAMILY, 'size' => 14, 'bold' => true, 'color' => '000000'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0]
            );
        $section->addTextBreak(1);

        // ── Meta grid (2 columns × 4 rows) ──
        $employee = $permit->employee;
        $rows = [
            ['Name',         $employee?->full_name ?? $permit->employee_name ?? '—',
             'Date Borrow', $this->date($permit->date_borrow)],
            ['Position',     $employee?->position ?? $permit->position_text ?? '—',
             'Date Return', $this->date($permit->date_return)],
            ['Department',   $employee?->department?->name ?? $permit->department_text ?? '—',
             'Purpose',     $permit->purpose],
            ['Destination',  $permit->destination,
             'Valid On',    "{$this->date($permit->valid_from)} — {$this->date($permit->valid_to)}"],
        ];
        $this->addMetaTable($section, $rows, [120, 220, 110, 220]);

        // ── Items ──
        $this->addSectionLabel($section, 'Items');
        $itemsTable = $section->addTable([
            'borderSize' => 6, 'borderColor' => '000000',
            'cellMargin' => 80, 'width' => 100, 'unit' => 'pct',
        ]);
        $itemsTable->addRow(360);
        $hdrFont  = ['name' => self::FONT_FAMILY, 'size' => 9, 'bold' => true, 'color' => '000000'];
        $hdrPara  = ['alignment' => Jc::CENTER];
        $hdrShade = ['bgColor' => 'F1F5F9', 'valign' => 'center'];
        foreach (['QTY' => 800, 'UNIT' => 900, 'DESCRIPTION' => 3500, 'SERIAL NO.' => 2000, 'REMARKS' => 3600] as $h => $w) {
            $itemsTable->addCell($w, $hdrShade)->addText($h, $hdrFont, $hdrPara);
        }
        $bodyFont = ['name' => self::FONT_FAMILY, 'size' => 10];
        $bodyPara = ['alignment' => Jc::CENTER];
        $items = $permit->items;
        foreach ($items as $item) {
            $itemsTable->addRow(360);
            $itemsTable->addCell(800,  ['valign' => 'center'])->addText((string) $item->qty, $bodyFont, $bodyPara);
            $itemsTable->addCell(900,  ['valign' => 'center'])->addText($item->unit, $bodyFont, $bodyPara);
            $itemsTable->addCell(3500, ['valign' => 'center'])->addText($item->description, $bodyFont, $bodyPara);
            $itemsTable->addCell(2000, ['valign' => 'center'])->addText($item->serial_no ?: 'N/A', $bodyFont, $bodyPara);
            $itemsTable->addCell(3600, ['valign' => 'center'])->addText($item->remarks ?: 'N/A', $bodyFont, $bodyPara);
        }
        // pad empty rows to a min of 4
        for ($i = $items->count(); $i < 4; $i++) {
            $itemsTable->addRow(360);
            for ($c = 0; $c < 5; $c++) {
                $itemsTable->addCell(2000, ['valign' => 'center'])->addText(' ', $bodyFont, $bodyPara);
            }
        }
        $section->addTextBreak(1);

        // ── Signatures ──
        $this->addSectionLabel($section, 'Signatures');
        $sigTable = $section->addTable(['cellMargin' => 140, 'width' => 100, 'unit' => 'pct',
            'borderSize' => 6, 'borderColor' => '000000']);

        $sigCellWidth = (int) ((self::PAGE_W - self::MARGIN_X * 2) / 2);
        $this->addSignatureCells($sigTable, [
            ['label' => 'Requested By', 'name' => $this->resolveName($permit->requestedBy?->name, $employee?->full_name)],
            ['label' => 'Issued By',    'name' => $permit->issuedBy?->name],
        ], $sigCellWidth);
        $this->addSignatureCells($sigTable, [
            ['label' => 'Noted By', 'name' =>
                trim(($permit->notedBy?->name ?? '') . ($permit->notedBy && $permit->notedBySecondary ? ' & ' : '') . ($permit->notedBySecondary?->name ?? '')),
                'role'  => 'IT Head & Supervisor'],
            ['label' => 'Approved By', 'name' => $permit->approval_note ?: ($permit->approvedBy?->name ?? ''),
                'italic' => (bool) $permit->approval_note,
                'role'  => 'Manager / COO / CFO / CEO'],
        ], $sigCellWidth);

        $section->addTextBreak(1);
        $section->addText('HELD LIABLE FOR ANY COSTS THAT WILL BE INCURRED',
            ['name' => self::FONT_FAMILY, 'size' => 9, 'italic' => true, 'color' => '475569'],
            ['alignment' => Jc::CENTER]);

        return $this->stream($word, "{$permit->permit_no}.docx");
    }

    public function incident(IncidentReport $report): StreamedResponse
    {
        $report->load(['asset.category', 'asset.brand', 'endUser.department',
            'preparedBy', 'notedBy', 'notedBySecondary', 'approvedBy']);

        $word = $this->newDocument();
        $section = $word->addSection($this->pageSetup());

        $this->addCompanyHeader($section, addressLine2: '18th Floor, Y - Tower, Macapagal Avenue Corner Coral Way Street',
            addressLine3: 'Pasay City, Metro Manila');
        $section->addTextBreak(1);

        // Meta: End User / Reported Problem
        $endUser = $report->endUser?->full_name ?? $report->end_user_name ?? '—';
        $this->addLabelRow($section, 'End User', $endUser);
        $this->addLabelRow($section, 'Reported Problem', $report->reported_problem);
        $section->addTextBreak(1);

        // Action Taken
        $this->addRuledSectionHeading($section, 'Action Taken:');
        $section->addText('Conducted troubleshoot & perform the following:',
            ['name' => self::FONT_FAMILY, 'size' => 11], ['spaceAfter' => 80]);
        foreach ($this->bullets($report->action_taken) as $line) {
            $this->addBullet($section, $line, '✓');
        }
        $section->addTextBreak(1);

        // Findings
        $this->addRuledSectionHeading($section, 'Findings/Possible Cause:');
        foreach ($this->bullets($report->findings) as $line) {
            $this->addBullet($section, $line);
        }
        $section->addTextBreak(1);

        // Quick Specs from asset
        if ($asset = $report->asset) {
            $section->addText('Computer Quick Specs & History:',
                ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true, 'underline' => 'single']);
            $specs = collect($asset->specifications ?? [])
                ->map(fn ($s) => is_array($s) ? ($s['value'] ?? '') : (string) $s)
                ->filter()->implode(' | ');
            $this->addLabelRow($section, 'Specification', $specs ?: '—', 160, 11);
            $this->addLabelRow($section, 'Serial Number', $asset->serial_number ?: '—', 160, 11);
            $this->addLabelRow($section, 'Purchase Date', $asset->purchase_date?->format('M d, Y') ?: '—', 160, 11);
            $this->addLabelRow($section, 'Deployed Date', $asset->deployment_date?->format('M d, Y') ?: '—', 160, 11);
            $lifespan = $asset->expected_lifespan_years ?? 5;
            $this->addLabelRow($section, 'Life Span', "{$lifespan} ({$lifespan}) YEARS", 160, 11);
            $section->addTextBreak(1);
        }

        // Recommendation
        $this->addRuledSectionHeading($section, 'Recommendation:');
        foreach ($this->bullets($report->recommendation) as $line) {
            $this->addBullet($section, $line);
        }
        $section->addTextBreak(1);

        $section->addText('———————————————————— Nothing Follows ————————————————————',
            ['name' => self::FONT_FAMILY, 'size' => 10, 'italic' => true, 'color' => '475569'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);

        // Signatures: 3 stacked left, then Approved By below on the right
        $this->addIRSignerLine($section, 'Prepared by', $report->preparedBy?->name, 'IT Associate - Junior Technical');
        $this->addIRSignerLine($section, 'Noted by',    $report->notedBy?->name,          'IT - MIS Head');
        $this->addIRSignerLine($section, 'Noted by',    $report->notedBySecondary?->name, 'IT - Technical Head');

        $section->addTextBreak(1);
        $section->addText('Approved By:',
            ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true],
            ['alignment' => Jc::END, 'spaceAfter' => 60]);
        $section->addText($report->approvedBy?->name ?: '',
            ['name' => self::FONT_FAMILY, 'size' => 11, 'italic' => true, 'bold' => true],
            ['alignment' => Jc::END, 'spaceAfter' => 40]);
        $section->addText('CFO – Executive',
            ['name' => self::FONT_FAMILY, 'size' => 10, 'bold' => true],
            ['alignment' => Jc::END]);

        return $this->stream($word, "{$report->ir_no}.docx");
    }

    public function recommendation(Recommendation $rec): StreamedResponse
    {
        $rec->load(['asset.category', 'requestor.department', 'preparedBy', 'reviewedBy', 'notedBy']);

        $word = $this->newDocument();
        $section = $word->addSection($this->pageSetup());

        $this->addCompanyHeader($section, addressLine2: '18th Floor, Y - Tower, Macapagal Avenue Corner Coral Way Street',
            addressLine3: 'Pasay City, Metro Manila');
        $section->addTextBreak(1);

        // Header rows
        $this->addLabelRow($section, 'Date', $rec->report_date?->format('F j, Y') ?? '');

        $name  = $rec->requestor?->full_name ?? $rec->requestor_name ?? '';
        $empId = $rec->requestor?->employee_no ?? $rec->requestor_employee_no ?? '';
        $pos   = $rec->requestor?->position ?? $rec->requestor_position ?? '';
        $dept  = $rec->requestor?->department?->name ?? $rec->requestor_department ?? '';

        $reqText = sprintf('NAME: %s', strtoupper($name));
        if ($empId) $reqText .= "\nEMPLOYEE ID: {$empId}";
        if ($pos)   $reqText .= "\nPOSITION: " . strtoupper($pos);
        if ($dept)  $reqText .= "\nDEPARTMENT: " . strtoupper($dept);

        // multi-line requestor cell
        $reqTable = $section->addTable(['cellMargin' => 60, 'width' => 100, 'unit' => 'pct']);
        $reqTable->addRow();
        $reqTable->addCell(2000, ['valign' => 'top'])->addText('Requestor :',
            ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $reqCell = $reqTable->addCell(self::PAGE_W - self::MARGIN_X * 2 - 2000, ['valign' => 'top']);
        foreach (explode("\n", $reqText) as $line) {
            $reqCell->addText($line, ['name' => self::FONT_FAMILY, 'size' => 11]);
        }
        $this->addLabelRow($section, 'Thru', $rec->thru);

        // Subject - bordered
        $subjTable = $section->addTable([
            'borderTopSize' => 8, 'borderBottomSize' => 8, 'borderTopColor' => '000000', 'borderBottomColor' => '000000',
            'cellMargin' => 100, 'width' => 100, 'unit' => 'pct',
        ]);
        $subjTable->addRow();
        $subjTable->addCell(2000, ['valign' => 'center'])->addText('Subject :',
            ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $subjTable->addCell(self::PAGE_W - self::MARGIN_X * 2 - 2000, ['valign' => 'center'])
            ->addText($rec->subject, ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $section->addTextBreak(1);

        // Body paragraph (justified)
        $section->addText($rec->body,
            ['name' => self::FONT_FAMILY, 'size' => 11],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 200]);

        // Quick specs
        if (!empty($rec->quick_specs)) {
            $section->addText('CURRENT DESKTOP - QUICK SPECS',
                ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true, 'underline' => 'single'],
                ['indentation' => ['left' => 720]]);
            foreach ($rec->quick_specs as $spec) {
                $label = $spec['label'] ?? '';
                $value = $spec['value'] ?? '';
                $note  = $spec['note']  ?? '';
                $tr = $section->addTextRun(['indentation' => ['left' => 720], 'spaceAfter' => 0]);
                $tr->addText("{$label}: ", ['name' => self::FONT_FAMILY, 'size' => 10, 'bold' => true]);
                $tr->addText($value, ['name' => self::FONT_FAMILY, 'size' => 10]);
                if ($note) $tr->addText(" – \"{$note}\"",
                    ['name' => self::FONT_FAMILY, 'size' => 10, 'italic' => true, 'color' => '334155']);
            }
            $section->addTextBreak(1);
        }

        $section->addText('———————————————————— Nothing Follows ————————————————————',
            ['name' => self::FONT_FAMILY, 'size' => 10, 'italic' => true, 'color' => '475569'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);

        // Signatures
        $sigTable = $section->addTable(['cellMargin' => 140, 'width' => 100, 'unit' => 'pct']);
        $cw = (int) ((self::PAGE_W - self::MARGIN_X * 2) / 2);

        // Row 1: Prepared By (single, left-aligned)
        $sigTable->addRow();
        $cell = $sigTable->addCell($cw, ['valign' => 'top']);
        $tr = $cell->addTextRun();
        $tr->addText('Prepared By: ', ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $tr->addText($rec->preparedBy?->name ?: '', ['name' => self::FONT_FAMILY, 'size' => 11, 'italic' => true]);
        $cell->addText('IT Associate – Junior Technical',
            ['name' => self::FONT_FAMILY, 'size' => 10, 'bold' => true]);
        $sigTable->addCell($cw); // empty right

        // Row 2: Review By (left) + Note by (right)
        $sigTable->addRow();
        $cell = $sigTable->addCell($cw, ['valign' => 'top']);
        $tr = $cell->addTextRun();
        $tr->addText('Review By: ', ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $tr->addText($rec->reviewedBy?->name ?: '', ['name' => self::FONT_FAMILY, 'size' => 11, 'italic' => true]);
        $cell->addText('IT Technical Head – Network Specialist',
            ['name' => self::FONT_FAMILY, 'size' => 10, 'bold' => true]);

        $cell = $sigTable->addCell($cw, ['valign' => 'top']);
        $tr = $cell->addTextRun(['alignment' => Jc::END]);
        $tr->addText('Note by: ', ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $tr->addText($rec->notedBy?->name ?: '', ['name' => self::FONT_FAMILY, 'size' => 11, 'italic' => true]);
        $cell->addText('CFO-Executive Department',
            ['name' => self::FONT_FAMILY, 'size' => 10, 'bold' => true], ['alignment' => Jc::END]);

        return $this->stream($word, "{$rec->doc_no}.docx");
    }

    // ─────────────── Helpers ───────────────

    private function newDocument(): PhpWord
    {
        $word = new PhpWord();
        $word->setDefaultFontName(self::FONT_FAMILY);
        $word->setDefaultFontSize(11);
        return $word;
    }

    private function pageSetup(): array
    {
        return [
            'pageSizeW'    => self::PAGE_W,
            'pageSizeH'    => self::PAGE_H,
            'marginTop'    => self::MARGIN_Y,
            'marginBottom' => self::MARGIN_Y,
            'marginLeft'   => self::MARGIN_X,
            'marginRight'  => self::MARGIN_X,
            'orientation'  => 'portrait',
        ];
    }

    private function addCompanyHeader($section, ?string $addressLine2 = null, ?string $addressLine3 = null): void
    {
        $table = $section->addTable(['cellMargin' => 60, 'width' => 100, 'unit' => 'pct']);
        $table->addRow();
        $logoCell = $table->addCell(1500, ['valign' => 'center']);
        $logoPath = public_path('arvin-logo.png');
        if (file_exists($logoPath)) {
            $logoCell->addImage($logoPath, ['height' => 60, 'alignment' => Jc::CENTER]);
        }
        $infoCell = $table->addCell(self::PAGE_W - self::MARGIN_X * 2 - 1500, ['valign' => 'center']);
        $infoCell->addText('Arvin International Marketing Inc.',
            ['name' => self::FONT_FAMILY, 'size' => 13, 'bold' => true]);
        $infoCell->addText($addressLine2 ?? '15th Flr Unit B, A-Place Building Coral Way St., Macapagal Avenue, Pasay City',
            ['name' => self::FONT_FAMILY, 'size' => 10, 'color' => '334155']);
        if ($addressLine3) {
            $infoCell->addText($addressLine3, ['name' => self::FONT_FAMILY, 'size' => 10, 'color' => '334155']);
        } else {
            $infoCell->addText('Tel No.: 843-3676 to 80',
                ['name' => self::FONT_FAMILY, 'size' => 10, 'color' => '334155']);
        }
    }

    private function addMetaTable($section, array $rows, array $widths): void
    {
        $table = $section->addTable(['cellMargin' => 80, 'width' => 100, 'unit' => 'pct',
            'borderTopSize' => 4, 'borderBottomSize' => 4,
            'borderInsideHSize' => 4, 'borderInsideHColor' => 'cbd5e1',
            'borderTopColor' => '94a3b8', 'borderBottomColor' => '94a3b8']);
        foreach ($rows as $row) {
            $table->addRow(420);
            foreach ($row as $i => $val) {
                $cellW = $widths[$i] * 10; // approximate
                $isLabel = $i % 2 === 0;
                $table->addCell($cellW, ['valign' => 'center'])
                    ->addText((string) $val,
                        ['name' => self::FONT_FAMILY, 'size' => 11,
                         'bold' => $isLabel, 'color' => $isLabel ? '475569' : '0f172a'],
                        ['spaceAfter' => 0]);
            }
        }
    }

    private function addLabelRow($section, string $label, string $value, int $labelWidth = 2200, int $fontSize = 11): void
    {
        $tr = $section->addTextRun(['spaceAfter' => 40]);
        $tr->addText(str_pad($label, 22), ['name' => self::FONT_FAMILY, 'size' => $fontSize, 'bold' => true]);
        $tr->addText(': ', ['name' => self::FONT_FAMILY, 'size' => $fontSize, 'bold' => true]);
        $tr->addText($value, ['name' => self::FONT_FAMILY, 'size' => $fontSize]);
    }

    private function addRuledSectionHeading($section, string $text): void
    {
        $tr = $section->addTextRun(['spaceBefore' => 100, 'spaceAfter' => 60]);
        $tr->addText($text, ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $tr->addText(' ' . str_repeat('─', 80),
            ['name' => self::FONT_FAMILY, 'size' => 11, 'color' => '000000']);
    }

    private function addSectionLabel($section, string $text): void
    {
        $table = $section->addTable([
            'borderTopSize' => 4, 'borderBottomSize' => 4,
            'borderTopColor' => 'cbd5e1', 'borderBottomColor' => 'cbd5e1',
            'cellMargin' => 80, 'width' => 100, 'unit' => 'pct',
        ]);
        $table->addRow();
        $table->addCell(self::PAGE_W - self::MARGIN_X * 2, ['bgColor' => 'EFF6FF'])
            ->addText(strtoupper($text),
                ['name' => self::FONT_FAMILY, 'size' => 9, 'bold' => true, 'color' => '1D4ED8'],
                ['spaceBefore' => 0, 'spaceAfter' => 0]);
    }

    private function addSignatureCells($table, array $cells, int $cw): void
    {
        $table->addRow(900);
        foreach ($cells as $c) {
            $cell = $table->addCell($cw, ['valign' => 'center']);
            $cell->addText(strtoupper($c['name'] ?? ' '),
                ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true,
                 'italic' => $c['italic'] ?? false],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            $cell->addText($c['label'],
                ['name' => self::FONT_FAMILY, 'size' => 9, 'bold' => true, 'color' => '475569'],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            if (!empty($c['role'])) {
                $cell->addText($c['role'],
                    ['name' => self::FONT_FAMILY, 'size' => 9, 'italic' => true, 'color' => '475569'],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            }
        }
    }

    private function addIRSignerLine($section, string $label, ?string $name, string $role): void
    {
        $tr = $section->addTextRun(['spaceAfter' => 0]);
        $tr->addText("{$label}: ", ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $tr->addText($name ?: '', ['name' => self::FONT_FAMILY, 'size' => 11, 'italic' => true]);
        $section->addText($role,
            ['name' => self::FONT_FAMILY, 'size' => 10, 'bold' => true],
            ['indentation' => ['left' => 540], 'spaceAfter' => 180]);
    }

    private function addBullet($section, string $text, string $glyph = '•'): void
    {
        $tr = $section->addTextRun(['spaceAfter' => 40, 'indentation' => ['left' => 360, 'hanging' => 240]]);
        $tr->addText($glyph . '  ', ['name' => self::FONT_FAMILY, 'size' => 11, 'bold' => true]);
        $tr->addText($text, ['name' => self::FONT_FAMILY, 'size' => 11]);
    }

    private function bullets(?string $text): array
    {
        return collect(explode("\n", (string) $text))
            ->map(fn ($s) => trim($s))
            ->filter()->values()->all();
    }

    private function date($d): string
    {
        return $d instanceof Carbon ? $d->format('d-M-y') : (string) ($d ?? '');
    }

    private function resolveName(?string ...$candidates): string
    {
        foreach ($candidates as $c) {
            if ($c) return $c;
        }
        return '';
    }

    private function stream(PhpWord $word, string $filename): StreamedResponse
    {
        return new StreamedResponse(function () use ($word) {
            $writer = IOFactory::createWriter($word, 'Word2007');
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }
}
