<?php

namespace App\Services\Document;

use App\Models\Document;
use App\Services\Document\DocumentGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
// TODO: Install simple-qrcode package: composer require simplesoftwareio/simple-qrcode
// use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentService
{
    public function __construct(
        private DocumentGenerator $generator
    ) {}

    public function generateDocument(Document $document): Document
    {
        DB::beginTransaction();
        try {
            // Generate DOCX
            $docxPath = $this->generator->generateFromTemplate($document);

            // Try to convert to PDF
            $pdfPath = $this->generator->convertToPdf($docxPath);

            // Generate verify hash (QR code data)
            $verifyHash = $this->generateVerifyHash($document);

            // Generate QR code
            $qrCodePath = $this->generateQrCode($document, $verifyHash);

            // Update document
            $document->update([
                'verify_hash' => $verifyHash,
                // TODO: add file_path_docx, file_path_pdf, file_path_qr fields to documents table
            ]);

            DB::commit();

            return $document->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function generateVerifyHash(Document $document): string
    {
        $data = [
            'document_id' => $document->id,
            'type' => $document->type,
            'number' => $document->number,
            'date' => $document->date->format('Y-m-d'),
            'created_at' => $document->created_at->toIso8601String(),
        ];

        return hash('sha256', json_encode($data) . config('app.key'));
    }

    private function generateQrCode(Document $document, string $verifyHash): string
    {
        // TODO: Install simple-qrcode package and uncomment
        $verifyUrl = config('app.url') . '/api/documents/verify/' . $verifyHash;
        
        $qrCode = QrCode::format('png')
            ->size(300)
            ->generate($verifyUrl);

        $qrPath = 'documents/' . $document->id . '/qr_' . Str::uuid() . '.png';
        \Storage::disk('s3')->put($qrPath, $qrCode);

        // For now, return placeholder
        $qrPath = 'documents/' . $document->id . '/qr_placeholder.png';
        
        // TODO: Generate actual QR code when package is installed
        // For now, create placeholder file
        Storage::disk('s3')->put($qrPath, 'QR_CODE_PLACEHOLDER');
        
        return $qrPath;
    }

    public function verifyDocument(string $verifyHash): ?Document
    {
        $document = Document::where('verify_hash', $verifyHash)->first();

        if (!$document) {
            return null;
        }

        // Verify hash matches
        $expectedHash = $this->generateVerifyHash($document);
        if ($expectedHash !== $verifyHash) {
            return null;
        }

        return $document;
    }
}

