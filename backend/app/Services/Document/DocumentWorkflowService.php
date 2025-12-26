<?php

namespace App\Services\Document;

use App\Models\Document;
use App\Models\DocumentRoute;
use App\Models\DocumentAck;
use App\Services\Outbox\OutboxService;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Support\Events\EventTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentWorkflowService
{
    public function __construct(
        private OutboxService $outboxService,
        private NotificationService $notificationService
    ) {}

    public function sendToApproval(Document $document, array $route): void
    {
        if ($document->status !== 'draft') {
            throw new \Exception('Document must be in draft status');
        }

        DB::transaction(function () use ($document, $route) {
            // Clear existing routes
            $document->routes()->delete();

            foreach ($route as $step) {
                DocumentRoute::create([
                    'document_id' => $document->id,
                    'step_no' => $step['stepNo'],
                    'approver_role_id' => $step['approverRoleId'] ?? null,
                    'approver_user_id' => $step['approverUserId'] ?? null,
                    'status' => 'pending',
                ]);
            }

            $document->update(['status' => 'on_review']);

            AuditService::log(
                'document.send_to_approval',
                'document',
                $document->id,
                ['status' => 'draft'],
                ['status' => 'on_review', 'route' => $route],
                auth()->id()
            );

            $this->outboxService->record(
                EventTypes::DOCUMENT_STATUS_CHANGED,
                auth()->id(),
                'document',
                $document->id,
                ['status' => 'on_review']
            );

            $this->notifyCurrentStep($document);
        });
    }

    public function approveStep(Document $document, ?string $comment = null): void
    {
        $user = auth()->user();
        $currentStep = $this->getCurrentStepForUser($document, $user);

        if (!$currentStep) {
            throw new \Exception('No pending step found for current user');
        }

        DB::transaction(function () use ($document, $currentStep, $comment, $user) {
            $beforeStatus = $document->status;

            $currentStep->update([
                'status' => 'approved',
                'decided_at' => now(),
                'comment' => $comment,
            ]);

            $nextStep = $document->currentStep();

            if (!$nextStep) {
                $document->update(['status' => 'approved']);
                $afterStatus = 'approved';
            } else {
                $afterStatus = 'on_review';
                $this->notifyCurrentStep($document);
            }

            AuditService::log(
                'document.approve',
                'document',
                $document->id,
                ['status' => $beforeStatus, 'step_no' => $currentStep->step_no],
                ['status' => $afterStatus, 'step_no' => $currentStep->step_no, 'comment' => $comment],
                $user->id
            );

            $this->outboxService->record(
                EventTypes::DOCUMENT_STATUS_CHANGED,
                $user->id,
                'document',
                $document->id,
                ['status' => $afterStatus, 'step_no' => $currentStep->step_no]
            );
        });
    }

    public function rejectStep(Document $document, string $comment): void
    {
        $user = auth()->user();
        $currentStep = $this->getCurrentStepForUser($document, $user);

        if (!$currentStep) {
            throw new \Exception('No pending step found for current user');
        }

        DB::transaction(function () use ($document, $currentStep, $comment, $user) {
            $beforeStatus = $document->status;

            $currentStep->update([
                'status' => 'rejected',
                'decided_at' => now(),
                'comment' => $comment,
            ]);

            $document->update(['status' => 'draft']);

            AuditService::log(
                'document.reject',
                'document',
                $document->id,
                ['status' => $beforeStatus, 'step_no' => $currentStep->step_no],
                ['status' => 'draft', 'step_no' => $currentStep->step_no, 'comment' => $comment],
                $user->id
            );

            $this->outboxService->record(
                EventTypes::DOCUMENT_STATUS_CHANGED,
                $user->id,
                'document',
                $document->id,
                ['status' => 'draft', 'rejected_at_step' => $currentStep->step_no]
            );
        });
    }

    public function sign(Document $document, ?string $comment = null): void
    {
        if ($document->status !== 'approved') {
            throw new \Exception('Document must be approved before signing');
        }

        $user = auth()->user();

        DB::transaction(function () use ($document, $comment, $user) {
            $document->update([
                'status' => 'signed',
                'signed_by' => $user->id,
                'signed_at' => now(),
            ]);

            AuditService::log(
                'document.sign',
                'document',
                $document->id,
                ['status' => 'approved'],
                ['status' => 'signed', 'signed_by' => $user->id, 'comment' => $comment],
                $user->id
            );

            $this->outboxService->record(
                EventTypes::DOCUMENT_STATUS_CHANGED,
                $user->id,
                'document',
                $document->id,
                ['status' => 'signed']
            );

            $this->notifyAckRecipients($document);
        });
    }

    public function setAckTargets(Document $document, array $userIds): void
    {
        DB::transaction(function () use ($document, $userIds) {
            foreach ($userIds as $userId) {
                DocumentAck::updateOrCreate(
                    [
                        'document_id' => $document->id,
                        'user_id' => $userId,
                    ],
                    [
                        'status' => 'read',
                    ]
                );
            }
        });
    }

    public function confirmAck(Document $document, int $userId): void
    {
        DocumentAck::updateOrCreate(
            [
                'document_id' => $document->id,
                'user_id' => $userId,
            ],
            [
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]
        );
    }

    public function registerNumber(Document $document): void
    {
        if ($document->number) {
            throw new \Exception('Document already has a number');
        }

        DB::transaction(function () use ($document) {
            $registry = DB::table('document_registry')
                ->where('type', $document->type)
                ->where('year', now()->year)
                ->lockForUpdate()
                ->first();

            if (!$registry) {
                $lastNumber = 0;
                DB::table('document_registry')->insert([
                    'type' => $document->type,
                    'year' => now()->year,
                    'last_number' => 0,
                    'updated_at' => now(),
                ]);
            } else {
                $lastNumber = $registry->last_number;
            }

            $nextNumber = $lastNumber + 1;

            DB::table('document_registry')
                ->where('type', $document->type)
                ->where('year', now()->year)
                ->update(['last_number' => $nextNumber]);

            $document->update([
                'number' => (string) $nextNumber,
                'date' => $document->date ?? now()->toDateString(),
            ]);

            AuditService::log(
                'document.register',
                'document',
                $document->id,
                ['number' => null],
                ['number' => $nextNumber, 'date' => $document->date],
                auth()->id()
            );
        });
    }

    private function getCurrentStepForUser(Document $document, $user): ?DocumentRoute
    {
        $currentStep = $document->currentStep();
        if (!$currentStep) {
            return null;
        }

        // Check if user matches approver
        if ($currentStep->approver_user_id && $currentStep->approver_user_id === $user->id) {
            return $currentStep;
        }

        // Check if user has approver role
        if ($currentStep->approver_role_id) {
            $hasRole = $user->roles()->where('id', $currentStep->approver_role_id)->exists();
            if ($hasRole) {
                return $currentStep;
            }
        }

        return null;
    }

    private function notifyCurrentStep(Document $document): void
    {
        $currentStep = $document->currentStep();
        if (!$currentStep) {
            return;
        }

        $userIds = [];

        if ($currentStep->approver_role_id) {
            $userIds = DB::table('user_roles')
                ->where('role_id', $currentStep->approver_role_id)
                ->pluck('user_id')
                ->toArray();
        }

        if ($currentStep->approver_user_id) {
            $userIds[] = $currentStep->approver_user_id;
        }

        foreach (array_unique($userIds) as $userId) {
            $this->notificationService->create(
                $userId,
                'document.requires_approval',
                [
                    'document_id' => $document->id,
                    'document_number' => $document->number,
                    'step_no' => $currentStep->step_no,
                    'url' => "/documents/{$document->id}",
                ]
            );
        }
    }

    private function notifyAckRecipients(Document $document): void
    {
        // TODO: get ack recipients from document data or template
        // For now, placeholder
    }
}
