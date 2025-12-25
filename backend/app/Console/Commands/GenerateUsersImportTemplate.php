<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class GenerateUsersImportTemplate extends Command
{
    protected $signature = 'template:users-import {--output=docs/templates/users_import_template.xlsx}';

    protected $description = 'Generate XLSX template for users import';

    public function handle(): int
    {
        $outputPath = $this->option('output');
        $fullPath = base_path($outputPath);

        // Create directory if it doesn't exist
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'A1' => 'ФИО',
            'B1' => 'Email',
            'C1' => 'Телефон',
            'D1' => 'Пароль',
            'E1' => 'Статус',
            'F1' => 'Роли',
            'G1' => 'Группы',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style header row
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30); // ФИО
        $sheet->getColumnDimension('B')->setWidth(25); // Email
        $sheet->getColumnDimension('C')->setWidth(18); // Телефон
        $sheet->getColumnDimension('D')->setWidth(20); // Пароль
        $sheet->getColumnDimension('E')->setWidth(12); // Статус
        $sheet->getColumnDimension('F')->setWidth(25); // Роли
        $sheet->getColumnDimension('G')->setWidth(20); // Группы

        // Add example rows
        $examples = [
            [
                'Иванов Иван Иванович',
                'ivanov@test.local',
                '+79001234567',
                'Password123!',
                'active',
                'студент',
                '2ИС-31',
            ],
            [
                'Петрова Мария Сергеевна',
                'petrova@test.local',
                '+79001234568',
                '',
                'active',
                'студент,куратор',
                '1ПР-12',
            ],
            [
                'Сидоров Сидор Сидорович',
                '',
                '+79001234569',
                'MySecurePass123!',
                'active',
                'преподаватель',
                '',
            ],
        ];

        $row = 2;
        foreach ($examples as $example) {
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        // Style example rows
        $exampleStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D0D0'],
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
        ];

        $sheet->getStyle('A2:G' . ($row - 1))->applyFromArray($exampleStyle);

        // Add data validation for Status column
        $statusValidation = $sheet->getCell('E2')->getDataValidation();
        $statusValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $statusValidation->setFormula1('"active,blocked"');
        $statusValidation->setShowDropDown(true);
        $statusValidation->setAllowBlank(true);

        // Copy validation to all rows
        for ($i = 2; $i <= 100; $i++) {
            $sheet->getCell('E' . $i)->setDataValidation(clone $statusValidation);
        }

        // Freeze header row
        $sheet->freezePane('A2');

        // Write file
        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        $this->info("Template created successfully: {$outputPath}");
        $this->info("File size: " . number_format(filesize($fullPath) / 1024, 2) . " KB");

        return Command::SUCCESS;
    }
}

