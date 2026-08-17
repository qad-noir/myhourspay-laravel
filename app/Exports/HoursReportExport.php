<?php

namespace App\Exports;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HoursReportExport
{
    public function store(User $user, array $summary, string $start, string $end, string $path): void
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
        $sheet->setCellValue('A5', 'Generated');
        $sheet->setCellValue('B5', now(config('hours.timezone'))->format('Y-m-d H:i T'));
        $sheet->setCellValue('A6', 'Weekly target');
        $sheet->setCellValue('B6', sprintf('%02d:00', intdiv((int) config('hours.weekly_target_minutes'), 60)));
        $sheet->setCellValue('A7', 'Period total');
        $sheet->setCellValue('B7', $summary['total_formatted']);

        $headings = ['Date', 'Weekday', 'Start', 'End', 'Break minutes', 'Gross duration', 'Net duration', 'ISO week', 'Weekly total', 'Weekly variance', 'Notes'];
        $sheet->fromArray($headings, null, 'A9');

        $row = 10;
        foreach ($summary['entries'] as $entry) {
            $values = [
                $entry['work_date'], $entry['weekday'], $entry['start_time'], $entry['end_time'],
                $entry['break_minutes'], $entry['gross_formatted'], $entry['net_formatted'],
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

        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->setColor(new Color('FFFFFFFF'));
        $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F766E');
        $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A9:K9')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A9:K9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF115E59');
        $sheet->freezePane('A10');
        $sheet->setAutoFilter('A9:K'.max(9, $row - 1));
        foreach (range('A', 'K') as $column) {
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
