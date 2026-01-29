<?php

namespace Database\Seeders;

use App\Models\DocTemplate;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DocTemplate::firstOrCreate(
            ['name' => 'certificate_default'],
            [
                'type' => 'certificate',
                'schema_json' => [
                    'fields' => [
                        'fio' => ['type' => 'string', 'label' => 'ФИО'],
                        'contest' => ['type' => 'string', 'label' => 'Название конкурса'],
                        'place' => ['type' => 'integer', 'label' => 'Место'],
                        'score' => ['type' => 'decimal', 'label' => 'Балл'],
                        'date' => ['type' => 'date', 'label' => 'Дата'],
                    ],
                ],
                'file_template_key' => 'certificate_default.docx', // TODO: Upload actual template file
            ]
        );
    }
}







