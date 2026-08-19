<?php

namespace App\Exports;

use App\Models\User;
use App\Models\Workspace;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HoursReportExport
{
    public function store(User $user, Workspace $workspace, array $summary, string $start, string $end, string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hours report');

        $sheet->setCellValue('A1', 'myhourspay');
        $sheet->setCellValue('A2', 'Hours report');
        $sheet->setCellValue('A3', 'User');
        $sheet->setCellValueExplicit('B3', $user->name, DataType::TYPE_STRING);
        $sheet->setCellValue('A4', 'Period');
        $sheet->setCellValue('B4', "$start to $end");
        $sheet->setCellValue('A5', 'Workspace');
        $sheet->setCellValueExplicit('B5', $workspace->name, DataType::TYPE_STRING);
        $sheet->setCellValue('A6', 'Generated');
        $sheet->setCellValue('B6', now(config('hours.timezone'))->format('Y-m-d H:i T'));
        $sheet->setCellValue('A7', 'Weekly target');
        $sheet->setCellValue('B7', sprintf('%02d:%02d', intdiv($workspace->weekly_target_minutes, 60), $workspace->weekly_target_minutes % 60));
        $sheet->setCellValue('A8', 'Period total');
        $sheet->setCellValue('B8', $summary['total_formatted']);

        $headings = ['Date', 'Weekday', 'Start', 'End', 'Break type', 'Break minutes', 'Gross duration', 'Net duration', 'ISO week', 'Weekly total', 'Weekly variance', 'Notes'];
        $sheet->fromArray($headings, null, 'A10');

        $row = 11;
        foreach ($summary['entries'] as $entry) {
            $values = [
                $entry['work_date'], $entry['weekday'], $entry['start_time'], $entry['end_time'],
                ucfirst($entry['break_type']), $entry['break_minutes'], $entry['gross_formatted'], $entry['net_formatted'],
                $entry['week_key'].($entry['partial_week'] ? ' (partial)' : ''),
                $entry['weekly_total'], $entry['weekly_variance'], $this->safeText($entry['notes'] ?? ''),
            ];
            foreach ($values as $column => $value) {
                $coordinate = chr(65 + $column).$row;
                if (is_int($value)) {
                    $sheet->setCellValue($coordinate, $value);
                } else {
                    $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
                }
            }
            $row++;
        }

        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->setColor(new Color('FFFFFFFF'));
        $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F766E');
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A10:L10')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A10:L10')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF115E59');
        $sheet->freezePane('A11');
        $sheet->setAutoFilter('A10:L'.max(10, $row - 1));
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    public function safeText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/u', ltrim($value)) ? "'".$value : $value;
    }
}
