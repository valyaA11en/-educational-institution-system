<?php

namespace App\Services;

use App\Models\DocTemplate;
use App\Models\Document;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GostDocumentService
{
    /**
     * Генерировать документ из шаблона
     */
    public function generateFromTemplate(
        int $templateId,
        array $data,
        int $tenantId,
        ?int $userId = null
    ): Document {
        $template = DocTemplate::findOrFail($templateId);

        // Проверка типа шаблона (должен быть приказ или распоряжение)
        if (!in_array($template->type, ['order', 'decree', 'decision', 'приказ', 'распоряжение'])) {
            throw new \InvalidArgumentException('Template type must be order, decision, decree or their Russian equivalents');
        }

        // Загружаем шаблон (TODO: использовать file_template_key для получения файла)
        $templatePath = $this->getTemplatePath($template);
        if (!file_exists($templatePath)) {
            throw new \Exception("Template file not found: {$templatePath}");
        }

        // Обрабатываем шаблон DOCX
        $templateProcessor = new TemplateProcessor($templatePath);
        
        // Подготовка данных для шаблона
        $templateData = $this->prepareTemplateData($data);
        
        // Заменяем переменные в шаблоне
        foreach ($templateData as $key => $value) {
            try {
                $templateProcessor->setValue($key, $value ?? '');
            } catch (\Exception $e) {
                Log::warning("Failed to set template variable: {$key}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Сохраняем временный DOCX файл
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $tempDocxPath = $tempDir . '/' . uniqid('doc_', true) . '.docx';
        $templateProcessor->saveAs($tempDocxPath);

        // Генерируем PDF из DOCX
        $pdfPath = $this->convertDocxToPdf($tempDocxPath);

        // Сохраняем файлы
        $pdfFileName = 'documents/' . uniqid('pdf_', true) . '.pdf';
        Storage::disk('documents')->put($pdfFileName, file_get_contents($pdfPath));

        // Удаляем временные файлы
        @unlink($tempDocxPath);
        @unlink($pdfPath);

        // Создаем запись документа
        $document = Document::create([
            'template_id' => $templateId,
            'type' => $template->type,
            'number' => $data['number'] ?? '',
            'date' => $this->parseDate($data['date']) ?? now(),
            'status' => 'draft',
            'data_json' => $data,
            'created_by' => $userId,
            'verify_hash' => hash('sha256', uniqid('doc_', true) . time()),
        ]);

        // TODO: Сохранить путь к PDF файлу в data_json или добавить поле file_path

        return $document;
    }

    /**
     * Получить путь к файлу шаблона
     */
    protected function getTemplatePath(DocTemplate $template): string
    {
        // Получаем путь к файлу через file_template_key
        $fileKey = $template->file_template_key;
        if (!$fileKey) {
            throw new \Exception("Template file_template_key is not set for template: {$template->id}");
        }

        // Если file_template_key уже содержит расширение, используем как есть
        $defaultPath = storage_path('app/templates/' . $fileKey);
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }

        // Попробуем добавить .docx, если его нет
        if (!str_ends_with($fileKey, '.docx')) {
            $defaultPath = storage_path('app/templates/' . $fileKey . '.docx');
            if (file_exists($defaultPath)) {
                return $defaultPath;
            }
        }

        // Альтернативный путь по типу
        $alternativePath = storage_path('app/templates/' . $template->type . '.docx');
        if (file_exists($alternativePath)) {
            return $alternativePath;
        }

        throw new \Exception("Template file not found for template: {$template->id} (file_template_key: {$fileKey})");
    }

    /**
     * Конвертировать DOCX в PDF
     */
    protected function convertDocxToPdf(string $docxPath): ?string
    {
        $pdfPath = str_replace('.docx', '.pdf', $docxPath);

        // Используем LibreOffice для конвертации (требуется установка)
        // Проверяем наличие LibreOffice
        $libreOfficeCmd = 'libreoffice';
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows пути могут отличаться
            $libreOfficeCmd = 'soffice';
        }

        $command = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($libreOfficeCmd),
            escapeshellarg(dirname($pdfPath)),
            escapeshellarg($docxPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($pdfPath)) {
            // Если LibreOffice не установлен, возвращаем null (без конвертации)
            Log::warning('LibreOffice not available, PDF conversion skipped', [
                'docx_path' => $docxPath,
                'output' => implode("\n", $output),
                'return_code' => $returnCode,
            ]);
            return null;
        }

        return $pdfPath;
    }

    /**
     * Парсинг даты из формата ДД.ММ.ГГГГ
     */
    protected function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        return $date;
    }

    /**
     * Валидация данных документа согласно ГОСТ
     */
    public function validateGostData(array $data, string $type): array
    {
        $errors = [];

        // Обязательные поля для приказа и распоряжения (decision)
        if ($type === 'order' || $type === 'приказ' || $type === 'decision' || $type === 'распоряжение') {
            // Обязательные поля в data_json
            $requiredFields = [
                'org_name' => 'string',
                'title' => 'string',
                'basis' => 'string',
                'body_items' => 'array',
                'signer_name' => 'string',
                'signer_role' => 'string',
            ];

            foreach ($requiredFields as $field => $fieldType) {
                if (!isset($data[$field])) {
                    $errors[] = "Поле data_json.{$field} обязательно";
                } elseif ($fieldType === 'string' && empty(trim($data[$field]))) {
                    $errors[] = "Поле data_json.{$field} не может быть пустым";
                } elseif ($fieldType === 'array' && (!is_array($data[$field]) || empty($data[$field]))) {
                    $errors[] = "Поле data_json.{$field} должно быть непустым массивом";
                }
            }

            // Валидация body_items
            if (isset($data['body_items']) && is_array($data['body_items'])) {
                foreach ($data['body_items'] as $index => $item) {
                    if (!is_array($item)) {
                        $errors[] = "data_json.body_items[{$index}] должен быть объектом";
                        continue;
                    }
                    if (!isset($item['no']) || !isset($item['text'])) {
                        $errors[] = "data_json.body_items[{$index}] должен содержать поля 'no' и 'text'";
                    } elseif (empty(trim($item['text']))) {
                        $errors[] = "data_json.body_items[{$index}].text не может быть пустым";
                    }
                }
            }

            // Валидация формата номера (ГОСТ Р 6.30-2003)
            if (isset($data['number']) && !preg_match('/^\d+(-[А-Яа-я]+)?$/', $data['number'])) {
                $errors[] = 'Номер документа должен соответствовать формату: число или число-буква';
            }

            // Валидация даты
            if (isset($data['date']) && !$this->isValidDate($data['date'])) {
                $errors[] = 'Дата должна быть в формате ДД.ММ.ГГГГ';
            }

            // Опциональные поля (если есть, должны быть корректными)
            if (isset($data['appendix']) && !is_string($data['appendix']) && !is_array($data['appendix'])) {
                $errors[] = "Поле data_json.appendix должно быть строкой или массивом";
            }
            if (isset($data['recipients']) && !is_array($data['recipients'])) {
                $errors[] = "Поле data_json.recipients должно быть массивом";
            }
        }

        return $errors;
    }

    /**
     * Проверка валидности даты
     */
    protected function isValidDate(string $date): bool
    {
        // Формат ДД.ММ.ГГГГ
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $matches)) {
            return checkdate((int)$matches[2], (int)$matches[1], (int)$matches[3]);
        }
        return false;
    }

    /**
     * Подготовка данных для шаблона
     */
    protected function prepareTemplateData(array $data): array
    {
        $templateData = [];

        // Базовые поля
        $templateData['org_name'] = $data['org_name'] ?? '';
        $templateData['number'] = $data['number'] ?? '';
        $templateData['date'] = $data['date'] ?? '';
        $templateData['title'] = $data['title'] ?? '';
        $templateData['basis'] = $data['basis'] ?? '';
        $templateData['signer_role'] = $data['signer_role'] ?? '';
        $templateData['signer_name'] = $data['signer_name'] ?? '';

        // body_items - преобразуем в текст с нумерацией
        if (isset($data['body_items']) && is_array($data['body_items'])) {
            $bodyText = '';
            foreach ($data['body_items'] as $item) {
                if (isset($item['no']) && isset($item['text'])) {
                    $bodyText .= $item['no'] . '. ' . $item['text'] . "\n";
                }
            }
            $templateData['body_items'] = trim($bodyText);
        } else {
            $templateData['body_items'] = '';
        }

        // Опциональные поля
        if (isset($data['appendix'])) {
            $templateData['appendix'] = is_array($data['appendix']) 
                ? implode(', ', $data['appendix']) 
                : $data['appendix'];
        }
        if (isset($data['recipients']) && is_array($data['recipients'])) {
            $templateData['recipients'] = implode(', ', $data['recipients']);
        }

        return $templateData;
    }

    /**
     * Валидация документа
     */
    public function validateDocument(Document $document): array
    {
        return $this->validateGostData($document->data_json ?? [], $document->type);
    }

    /**
     * Генерировать приказ
     */
    public function generateOrder(array $data, int $tenantId, ?int $userId = null): Document
    {
        $template = DocTemplate::where(function ($q) {
            $q->where('type', 'order')
              ->orWhere('type', 'приказ');
        })->first();

        if (!$template) {
            throw new \Exception('Order template not found');
        }

        $errors = $this->validateGostData($data, 'order');
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }

        return $this->generateFromTemplate($template->id, $data, $tenantId, $userId);
    }

    /**
     * Генерировать распоряжение
     */
    public function generateDecree(array $data, int $tenantId, ?int $userId = null): Document
    {
        $template = DocTemplate::where(function ($q) {
            $q->where('type', 'decision')
              ->orWhere('type', 'распоряжение')
              ->orWhere('type', 'decree');
        })->first();

        if (!$template) {
            throw new \Exception('Decision template not found');
        }

        $errors = $this->validateGostData($data, 'decision');
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }

        return $this->generateFromTemplate($template->id, $data, $tenantId, $userId);
    }
}
