<?php

namespace App\Services\Document;

use App\Models\Document;
use App\Models\DocTemplate;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentGenerator
{
    public function generateFromTemplate(Document $document): string
    {
        $template = DocTemplate::findOrFail($document->template_id);

        if (!$template->file_template_key) {
            throw new \Exception('Template file not found');
        }

        // Download template from storage
        $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.docx';
        
        if (Storage::disk('s3')->exists($template->file_template_key)) {
            file_put_contents($tempPath, Storage::disk('s3')->get($template->file_template_key));
            $templatePath = $tempPath;
        } else {
            // Try local fallback
            $localPath = storage_path('app/templates/' . basename($template->file_template_key));
            if (!file_exists($localPath)) {
                throw new \Exception('Template file not found in storage');
            }
            $templatePath = $localPath;
        }

        $templateProcessor = new TemplateProcessor($templatePath);
        $data = $document->data_json ?? [];

        // Replace placeholders
        foreach ($data as $key => $value) {
            $templateProcessor->setValue($key, $value ?? '');
        }

        // Generate output path
        $outputPath = 'documents/' . $document->id . '/' . Str::uuid() . '.docx';
        $tempOutput = sys_get_temp_dir() . '/' . Str::uuid() . '.docx';
        $templateProcessor->saveAs($tempOutput);

        // Upload to storage
        Storage::disk('s3')->put($outputPath, file_get_contents($tempOutput));

        // Cleanup
        @unlink($tempOutput);
        if (isset($tempPath) && str_starts_with($tempPath, sys_get_temp_dir())) {
            @unlink($tempPath);
        }

        return $outputPath;
    }

    public function convertToPdf(string $docxPath): ?string
    {
        // TODO: Implement PDF conversion
        // Options:
        // 1. Use LibreOffice headless: libreoffice --headless --convert-to pdf
        // 2. Use external service (CloudConvert, etc.)
        // 3. Use PHP library (limited support)

        // For now, return null (PDF conversion not implemented)
        return null;
    }
}

