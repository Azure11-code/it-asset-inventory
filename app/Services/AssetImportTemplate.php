<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Location;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetImportTemplate
{
    public function stream(): StreamedResponse
    {
        $book = new Spreadsheet();
        $this->buildAssetsSheet($book);
        $this->buildValidValuesSheet($book);
        $book->setActiveSheetIndex(0);

        return new StreamedResponse(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="asset-import-template.xlsx"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    private function buildAssetsSheet(Spreadsheet $book): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Assets');

        $headers  = array_values(AssetXlsxImporter::COLUMNS);
        $lastCol  = count($headers);
        $lastColL = Coordinate::stringFromColumnIndex($lastCol);

        // Title block
        $sheet->setCellValue('A1', 'IT Asset Import Template');
        $sheet->mergeCells("A1:{$lastColL}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue('A2', 'Fill one asset per row starting at row 5. Delete the EX- example row before uploading. See "Valid Values" tab for allowed Category / Brand / Condition / Location / Status names.');
        $sheet->mergeCells("A2:{$lastColL}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '475569'], 'italic' => true],
            'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(40);

        // Header row at row 4 (row 3 blank spacer). Import service finds it by scanning for "Asset Tag".
        $headerRow = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, $headerRow], $h);
        }
        $sheet->getStyle("A{$headerRow}:{$lastColL}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);
        $sheet->freezePane('A' . ($headerRow + 1));

        // Mark required columns (Asset Tag, Category) with a red asterisk hint via cell comment
        $sheet->getComment([1, $headerRow])->getText()->createTextRun('Required — must be unique');
        $sheet->getComment([2, $headerRow])->getText()->createTextRun('Required — must match a Category name from the Valid Values tab');

        // Example row (row 5), light gray so users know it's a placeholder
        $exampleRow = $headerRow + 1;
        $firstCategory  = Category::orderBy('name')->value('name') ?? 'Laptop';
        $firstBrand     = Brand::orderBy('name')->value('name');
        $firstCondition = Condition::where('is_active', true)->orderBy('sort_order')->value('name');
        $firstLocation  = Location::orderBy('name')->value('name');

        $example = [
            'EX-LPT-001',                        // Asset Tag (sentinel prefix; importer skips it)
            $firstCategory,                       // Category
            $firstBrand ?: 'Dell',                // Brand
            'Latitude 5420',                      // Model
            'SN123456789',                        // Serial No.
            'Business laptop for finance team',   // Description
            "OS Name: Windows 11 Pro\nProcessor: Intel Core i5-1145G7\nRAM: 16 GB", // Specifications
            '2026-01-15',                         // Purchase Date
            '2026-01-20',                         // Deployment Date
            45000,                                // Purchase Cost
            5,                                    // Lifespan (Years)
            '',                                   // Warranty Until (auto: purchase + lifespan)
            $firstCondition ?: 'New',             // Condition
            'in_stock',                           // Status
            $firstLocation ?: '',                 // Location
            'Sample row — delete before import',  // Notes
        ];
        foreach ($example as $i => $v) {
            $sheet->setCellValue([$i + 1, $exampleRow], $v);
        }
        $sheet->getStyle("A{$exampleRow}:{$lastColL}{$exampleRow}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($exampleRow)->setRowHeight(60);

        // Auto-size columns; cap wide ones
        for ($c = 1; $c <= $lastCol; $c++) {
            $letter = Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $specsL = Coordinate::stringFromColumnIndex(array_search('specifications', array_keys(AssetXlsxImporter::COLUMNS)) + 1);
        $sheet->getColumnDimension($specsL)->setAutoSize(false);
        $sheet->getColumnDimension($specsL)->setWidth(45);
        $descL  = Coordinate::stringFromColumnIndex(array_search('description', array_keys(AssetXlsxImporter::COLUMNS)) + 1);
        $sheet->getColumnDimension($descL)->setAutoSize(false);
        $sheet->getColumnDimension($descL)->setWidth(30);
        $notesL = Coordinate::stringFromColumnIndex(array_search('notes', array_keys(AssetXlsxImporter::COLUMNS)) + 1);
        $sheet->getColumnDimension($notesL)->setAutoSize(false);
        $sheet->getColumnDimension($notesL)->setWidth(30);

        $sheet->getStyle("A{$headerRow}:{$lastColL}{$exampleRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
    }

    private function buildValidValuesSheet(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Valid Values');

        $sections = [
            'Categories' => Category::orderBy('name')->pluck('name')->all(),
            'Brands'     => Brand::orderBy('name')->pluck('name')->all(),
            'Conditions' => Condition::where('is_active', true)->orderBy('sort_order')->pluck('name')->all(),
            'Locations'  => Location::orderBy('name')->pluck('name')->all(),
            'Statuses'   => ['in_stock', 'assigned', 'for_repair', 'defective', 'retired', 'replaced'],
        ];

        $col = 1;
        foreach ($sections as $title => $values) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue([$col, 1], $title);
            $sheet->getStyle("{$letter}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            foreach ($values as $i => $v) {
                $sheet->setCellValue([$col, $i + 2], $v);
            }
            $sheet->getColumnDimension($letter)->setAutoSize(true);
            $col++;
        }
        $sheet->freezePane('A2');
    }
}
