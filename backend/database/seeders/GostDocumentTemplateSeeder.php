<?php

namespace Database\Seeders;

use App\Models\DocTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class GostDocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Приказ (order)
        $orderTemplate = DocTemplate::firstOrCreate(
            ['name' => 'order_gost_default'],
            [
                'type' => 'order',
                'schema_json' => [
                    'fields' => [
                        'org_name' => ['type' => 'string', 'label' => 'Название организации', 'required' => true],
                        'number' => ['type' => 'string', 'label' => 'Номер', 'required' => true],
                        'date' => ['type' => 'date', 'label' => 'Дата', 'required' => true],
                        'title' => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
                        'basis' => ['type' => 'string', 'label' => 'Основание', 'required' => true],
                        'body_items' => ['type' => 'array', 'label' => 'Пункты документа', 'required' => true],
                        'signer_role' => ['type' => 'string', 'label' => 'Должность подписанта', 'required' => true],
                        'signer_name' => ['type' => 'string', 'label' => 'ФИО подписанта', 'required' => true],
                        'appendix' => ['type' => 'string', 'label' => 'Приложение', 'required' => false],
                        'recipients' => ['type' => 'array', 'label' => 'Получатели', 'required' => false],
                    ],
                ],
                'file_template_key' => 'order_gost_default.docx',
            ]
        );

        // Распоряжение (decision)
        $decisionTemplate = DocTemplate::firstOrCreate(
            ['name' => 'decision_gost_default'],
            [
                'type' => 'decision',
                'schema_json' => [
                    'fields' => [
                        'org_name' => ['type' => 'string', 'label' => 'Название организации', 'required' => true],
                        'number' => ['type' => 'string', 'label' => 'Номер', 'required' => true],
                        'date' => ['type' => 'date', 'label' => 'Дата', 'required' => true],
                        'title' => ['type' => 'string', 'label' => 'Заголовок', 'required' => true],
                        'basis' => ['type' => 'string', 'label' => 'Основание', 'required' => true],
                        'body_items' => ['type' => 'array', 'label' => 'Пункты документа', 'required' => true],
                        'signer_role' => ['type' => 'string', 'label' => 'Должность подписанта', 'required' => true],
                        'signer_name' => ['type' => 'string', 'label' => 'ФИО подписанта', 'required' => true],
                        'appendix' => ['type' => 'string', 'label' => 'Приложение', 'required' => false],
                        'recipients' => ['type' => 'array', 'label' => 'Получатели', 'required' => false],
                    ],
                ],
                'file_template_key' => 'decision_gost_default.docx',
            ]
        );

        // Создаем директорию для шаблонов, если её нет
        $templatesDir = storage_path('app/templates');
        if (!is_dir($templatesDir)) {
            mkdir($templatesDir, 0755, true);
        }

        // Создаем простые DOCX файлы-заглушки (в реальности здесь должны быть реальные шаблоны)
        // Файлы должны быть загружены вручную или через систему управления файлами
        // Здесь мы только создаём записи в БД
    }
}


