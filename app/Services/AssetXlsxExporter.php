<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetXlsxExporter
{
    private const HEADERS = [
        'Asset Tag', 'Category', 'Brand', 'Model', 'Serial No.',
        'Status', 'Holder', 'Department', 'Location',
        'Purchase Date', 'Deployed Date', 'Warranty Until', 'Warranty Status',
        'Age', 'In Service', 'Eligible for Replacement', 'Specifications',
    ];

    public function stream(Builder $query, string $filename = 'assets.xlsx'): StreamedResponse
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Assets');

        $lastCol       = count(self::HEADERS);
        $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

        // ── Title block (rows 1–3) ──
        $sheet->setCellValue('A1', 'ARVIN INTERNATIONAL MARKETING INC.');
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue('A2', 'IT Asset Inventory Export');
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Row 3 holds the count + timestamp; we backfill the count after streaming.
        $sheet->mergeCells("A3:{$lastColLetter}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['size' => 10, 'color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(16);

        // ── Column headers (row 5; row 4 left blank as a spacer) ──
        $headerRow = 5;
        foreach (self::HEADERS as $i => $h) {
            $sheet->setCellValue([$i + 1, $headerRow], $h);
        }
        $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);
        $sheet->freezePane('A' . ($headerRow + 1));

        // ── Body rows ──
        $row   = $headerRow + 1;
        $count = 0;
        $query
            ->with([
                'brand:id,name',
                'category:id,name',
                'currentHolder:id,first_name,middle_name,last_name,department_id',
                'currentHolder.department:id,name',
                'currentLocation:id,name',
            ])
            ->chunk(500, function ($chunk) use ($sheet, &$row, &$count) {
                foreach ($chunk as $a) {
                    $sheet->setCellValue([1,  $row], (string) $a->asset_tag);
                    $sheet->setCellValue([2,  $row], (string) ($a->category?->name ?? ''));
                    $sheet->setCellValue([3,  $row], (string) ($a->brand?->name ?? ''));
                    $sheet->setCellValue([4,  $row], (string) ($a->model ?? ''));
                    $sheet->setCellValue([5,  $row], (string) ($a->serial_number ?? ''));
                    $sheet->setCellValue([6,  $row], str_replace('_', ' ', (string) $a->current_status));
                    $sheet->setCellValue([7,  $row], (string) ($a->currentHolder?->full_name ?? ''));
                    $sheet->setCellValue([8,  $row], (string) ($a->currentHolder?->department?->name ?? ''));
                    $sheet->setCellValue([9,  $row], (string) ($a->currentLocation?->name ?? ''));
                    $sheet->setCellValue([10, $row], (string) ($a->purchase_date?->format('Y-m-d') ?? ''));
                    $sheet->setCellValue([11, $row], (string) ($a->deployment_date?->format('Y-m-d') ?? ''));
                    $sheet->setCellValue([12, $row], (string) ($a->warranty_until?->format('Y-m-d') ?? ''));
                    $sheet->setCellValue([13, $row], str_replace('_', ' ', (string) $a->warranty_status));
                    $sheet->setCellValue([14, $row], (string) ($a->age_formatted ?? ''));
                    $sheet->setCellValue([15, $row], (string) ($a->service_duration_formatted ?? ''));
                    $sheet->setCellValue([16, $row], $a->is_eligible_for_replacement ? 'Yes' : 'No');
                    $sheet->setCellValue([17, $row], $this->formatSpecs($a->specifications));
                    $row++;
                    $count++;
                }
            });

        // Backfill the count line now that we know the total.
        $sheet->setCellValue('A3', sprintf(
            'Total: %d %s  •  Exported: %s',
            $count,
            $count === 1 ? 'asset' : 'assets',
            now()->format('Y-m-d H:i')
        ));

        // ── Auto-size columns ──
        for ($c = 1; $c <= count(self::HEADERS); $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        // Specifications column tends to be wide — cap it and wrap.
        $specsLetter = Coordinate::stringFromColumnIndex(17);
        $sheet->getColumnDimension($specsLetter)->setAutoSize(false);
        $sheet->getColumnDimension($specsLetter)->setWidth(60);
        $firstDataRow = $headerRow + 1;
        $lastRow      = max($row - 1, $firstDataRow);
        $sheet->getStyle("{$specsLetter}{$firstDataRow}:{$specsLetter}{$lastRow}")
            ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        // ── Borders on header + data range only (skip the merged title block) ──
        if ($count > 0) {
            $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
        }

        return new StreamedResponse(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    private function formatSpecs($specs): string
    {
        if (!is_array($specs) || empty($specs)) return '';
        return collect($specs)->map(function ($s) {
            if (is_array($s)) {
                $k = $s['key'] ?? $s['label'] ?? '';
                $v = $s['value'] ?? '';
                return $k ? "{$k}: {$v}" : $v;
            }
            return (string) $s;
        })->filter()->implode("\n");
    }
}
