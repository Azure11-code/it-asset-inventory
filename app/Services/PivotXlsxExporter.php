<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PivotXlsxExporter
{
    /**
     * Stream a 2-D pivot as .xlsx.
     *
     * @param string $title       Title shown in row 1 (merged), e.g. "Category × Department"
     * @param string $rowLabel    Label for the row-header column, e.g. "Category"
     * @param array  $columns     Column labels in order, e.g. ['ACCOUNTING','AUDIT',...]
     * @param array  $rows        Each row: ['label' => 'Desktop', 'cells' => ['ACCOUNTING' => 53, ...], 'total' => 197]
     * @param string $filename    Output filename
     */
    public function stream(string $title, string $rowLabel, array $columns, array $rows, string $filename): StreamedResponse
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Pivot');

        $colCount     = count($columns);
        $lastColIndex = $colCount + 2; // row label + N columns + Total
        $lastColL     = Coordinate::stringFromColumnIndex($lastColIndex);

        // ── Title (row 1) ──
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastColL}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // ── Meta line (row 2) ──
        $sheet->setCellValue('A2', 'Exported: ' . now()->format('Y-m-d H:i'));
        $sheet->mergeCells("A2:{$lastColL}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // ── Header row (row 4; row 3 blank spacer) ──
        $headerRow = 4;
        $sheet->setCellValue([1, $headerRow], $rowLabel);
        foreach ($columns as $i => $c) {
            $sheet->setCellValue([$i + 2, $headerRow], $c);
        }
        $sheet->setCellValue([$lastColIndex, $headerRow], 'Grand Total');
        $sheet->getStyle("A{$headerRow}:{$lastColL}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);
        $sheet->freezePane('B' . ($headerRow + 1));

        // ── Body rows ──
        $r = $headerRow + 1;
        $colTotals   = array_fill_keys($columns, 0);
        $grandTotal  = 0;
        foreach ($rows as $row) {
            $sheet->setCellValue([1, $r], $row['label']);
            $rowTotal = 0;
            foreach ($columns as $i => $c) {
                $v = (int) ($row['cells'][$c] ?? 0);
                if ($v > 0) $sheet->setCellValue([$i + 2, $r], $v);
                $rowTotal        += $v;
                $colTotals[$c]   += $v;
            }
            $sheet->setCellValue([$lastColIndex, $r], $rowTotal);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->getStyle([$lastColIndex, $r, $lastColIndex, $r])->getFont()->setBold(true);
            $grandTotal += $rowTotal;
            $r++;
        }

        // ── Grand Total row ──
        if (!empty($rows)) {
            $sheet->setCellValue([1, $r], 'Grand Total');
            foreach ($columns as $i => $c) {
                if ($colTotals[$c] > 0) $sheet->setCellValue([$i + 2, $r], $colTotals[$c]);
            }
            $sheet->setCellValue([$lastColIndex, $r], $grandTotal);
            $sheet->getStyle("A{$r}:{$lastColL}{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            ]);
        }
        $lastRow = $r;

        // ── Borders + auto-width ──
        $sheet->getStyle("A{$headerRow}:{$lastColL}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        for ($c = 1; $c <= $lastColIndex; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        return new StreamedResponse(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }
}
