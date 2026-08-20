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
        $sheet->setCellValue('A9', 'Overtime');
        $sheet->setCellValue('B9', $summary['overtime_formatted']);
        $sheet->setCellValue('A10', 'Breaks logged');
        $sheet->setCellValue('B10', $summary['break_count']);
        $sheet->setCellValue('A11', 'Paid breaks included');
        $sheet->setCellValue('B11', $summary['paid_break_formatted']);
        $sheet->setCellValue('A12', 'Unpaid breaks deducted');
        $sheet->setCellValue('B12', $summary['unpaid_break_formatted']);
        $sheet->setCellValue('A13', 'Workspace default break');
        $sheet->setCellValue('B13', ucfirst($workspace->default_break_type).' · '.$workspace->default_break_minutes.' minutes');

        $headings = ['Date', 'Weekday', 'Start', 'End', 'Break type', 'Break minutes', 'Hours worked', 'ISO week', 'Weekly total', 'Weekly variance', 'Weekly overtime', 'Notes'];
        $sheet->fromArray($headings, null, 'A15');

        $row = 16;
        foreach ($summary['entries'] as $entry) {
            $values = [
                $entry['work_date'], $entry['weekday'], $entry['start_time'], $entry['end_time'],
                ucfirst($entry['break_type']), $entry['break_minutes'], $entry['net_formatted'],
                $entry['week_key'].($entry['partial_week'] ? ' (partial)' : ''),
                $entry['weekly_total'], $entry['weekly_variance'], $entry['weekly_overtime_formatted'], $this->safeText($entry['notes'] ?? ''),
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
        $sheet->getStyle('A15:L15')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A15:L15')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF115E59');
        $sheet->freezePane('A16');
        $sheet->setAutoFilter('A15:L'.max(15, $row - 1));
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
