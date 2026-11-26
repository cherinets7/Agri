<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Mirrors the Word matrix: ተ/ቁ | ስትራተጂክ ግብ | ዋና ተግባር | መለኪያ | ዞናዊ ድምር (ዕቅድ, ክንውን) | የመዋቅሮች አፈፃፀም (ወረዳዎች).
 * $detail adds plan and % under every woreda. Zone columns appear only when the viewer is zone.
 */
class MatrixExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private const HEADER_ROWS = 4;

    /** @var list<array{0:int,1:int}> first and last data row (1-based) of every goal */
    private array $goalRanges = [];

    private int $columnCount = 4;

    private int $lastRow = self::HEADER_ROWS;

    public function __construct(private array $matrix, private bool $detail = false) {}

    public function title(): string
    {
        return 'ማትሪክስ '.$this->matrix['year'];
    }

    public function array(): array
    {
        $m = $this->matrix;
        $showZone = $m['showZone'];
        $woredas = $m['woredas'];

        $mode = $m['weekOnly'] ? 'የዚህ ሳምንት ክንውን' : 'ድምር ክንውን እስከ ሳምንቱ';
        $through = $m['through'] ? ' · '.et_date($m['through']).' ('.$m['through']->toDateString().')' : '';
        $filter = $m['approvedOnly'] ? ' · የጸደቁ ብቻ' : ' · የተላኩና የጸደቁ';

        $title = ['የየም ዞን ግብርና መምሪያ — ሳምንታዊ ተግባር አፈፃፀም መከታተያና ሪፖርት ማቅረቢያ ማትሪክስ'];
        $sub = ["{$m['year']} ዓ/ም · {$mode}{$through}{$filter}"];

        $groupRow = ['ተ/ቁ', 'የተቋሙ ዋና ዋና ስትራተጂክ ግቦች', 'ግቦችን ለማሳካት የሚከናወኑ ዋና ዋና ተግባራት', 'መለኪያ'];
        $subRow = ['', '', '', ''];
        if ($showZone) {
            array_push($groupRow, 'ዞናዊ ድምር', '', '');
            array_push($subRow, 'ዕቅድ', 'ክንውን', '%');
        }
        $perWoreda = $this->detail ? 3 : 1;
        foreach ($woredas as $i => $woreda) {
            $groupRow[] = $i === 0 ? 'የመዋቅሮች አፈፃፀም' : '';
            if ($this->detail) {
                array_push($subRow, "{$woreda->name_am}: ዕቅድ", "{$woreda->name_am}: ክንውን", "{$woreda->name_am}: %");
                array_push($groupRow, '', '');
            } else {
                $subRow[] = $woreda->name_am;
            }
        }

        $this->columnCount = count($groupRow);
        $rows = [$title, $sub, $groupRow, $subRow];

        $line = self::HEADER_ROWS;
        foreach ($m['goals'] as $goalBlock) {
            if (empty($goalBlock['rows'])) {
                continue;
            }
            $first = $line + 1;
            foreach ($goalBlock['rows'] as $i => $row) {
                $line++;
                $data = [
                    $i === 0 ? $goalBlock['goal']->number : null,
                    $i === 0 ? $goalBlock['goal']->name_am : null,
                    $row['activity']->name_am,
                    $row['activity']->unit->label(),
                ];
                if ($showZone) {
                    array_push($data, $row['zone_plan'], $row['zone_actual'], $row['zone_pct']);
                }
                foreach ($woredas as $woreda) {
                    $cell = $row['cells'][$woreda->id];
                    if ($this->detail) {
                        array_push($data, $cell['plan'], $cell['actual'], $cell['pct']);
                    } else {
                        $data[] = $cell['actual'];
                    }
                }
                $rows[] = $data;
            }
            $this->goalRanges[] = [$first, $line];
        }

        $this->lastRow = $line;

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = Coordinate::stringFromColumnIndex($this->columnCount);
                $headerEnd = self::HEADER_ROWS;
                $showZone = $this->matrix['showZone'];

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");
                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->mergeCells("{$col}3:{$col}4");
                }

                $next = 5;
                if ($showZone) {
                    $sheet->mergeCells('E3:G3');
                    $next = 8;
                }
                $woredaColumns = $this->columnCount - $next + 1;
                if ($woredaColumns > 1) {
                    $start = Coordinate::stringFromColumnIndex($next);
                    $sheet->mergeCells("{$start}3:{$lastCol}3");
                }

                foreach ($this->goalRanges as [$first, $last]) {
                    if ($last > $first) {
                        $sheet->mergeCells("A{$first}:A{$last}");
                        $sheet->mergeCells("B{$first}:B{$last}");
                    }
                }

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A3:{$lastCol}{$headerEnd}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6EFE4']],
                ]);

                $body = "A3:{$lastCol}{$this->lastRow}";
                $sheet->getStyle($body)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                if ($this->lastRow > $headerEnd) {
                    $dataRange = 'A'.($headerEnd + 1).":D{$this->lastRow}";
                    $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                    $numbers = 'E'.($headerEnd + 1).":{$lastCol}{$this->lastRow}";
                    $sheet->getStyle($numbers)->getNumberFormat()->setFormatCode('#,##0.###');
                }
                $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(32);
                $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(44);
                $sheet->freezePane('E5');
            },
        ];
    }
}
