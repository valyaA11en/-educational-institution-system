<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use App\Support\Security\AccessScopeService;
use Illuminate\Support\Facades\DB;

class FilePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Files are accessed through assignments/materials
    }

    public function view(User $user, File $file): bool
    {
        // Uploader can always view
        if ($user->id === $file->uploaded_by) {
            return true;
        }

        // Check relationships
        return $this->canAccessSubmission($user, $file)
            || $this->canAccessDocumentAttachment($user, $file)
            || $this->canAccessMaterialAttachment($user, $file);
    }

    public function download(User $user, File $file): bool
    {
        return $this->view($user, $file);
    }

    public function create(User $user): bool
    {
        // Any authenticated user can presign, but limits apply on submit/attach
        return true;
    }

    public function presign(User $user): bool
    {
        // Any authenticated user, but size limits apply on submit/attach
        return true;
    }

    public function delete(User $user, File $file): bool
    {
        return $user->id === $file->uploaded_by;
    }

    public function canAccessSubmission(User $user, File $file): bool
    {
        // Check if file belongs to assignment submission via submission_files
        $submissionFile = DB::table('submission_files')
            ->where('file_id', $file->id)
            ->first();

        if (!$submissionFile) {
            return false;
        }

        $submission = DB::table('assignment_submissions')
            ->find($submissionFile->submission_id);

        if (!$submission) {
            return false;
        }

        $scope = AccessScopeService::forUser($user);

        // Student can access their own submission
        if ($submission->student_user_id === $user->id) {
            return true;
        }

        // Teacher can access if they can grade the assignment
        $assignment = DB::table('assignments')->find($submission->assignment_id);
        if ($assignment && $assignment->teacher_user_id === $user->id) {
            return true;
        }

        // Check via AssignmentPolicy
        if ($assignment) {
            $assignmentModel = \App\Models\Assignment::find($assignment->id);
            if ($assignmentModel) {
                return app(\App\Policies\AssignmentPolicy::class)->grade($user, $assignmentModel);
            }
        }

        return false;
    }

    public function canAccessDocumentAttachment(User $user, File $file): bool
    {
        // Check if file belongs to document
        $attachment = DB::table('document_attachments')
            ->where('file_id', $file->id)
            ->first();

        if (!$attachment) {
            return false;
        }

        $document = DB::table('documents')->find($attachment->document_id);
        if (!$document) {
            return false;
        }

        // Use DocumentPolicy to check access
        $documentModel = \App\Models\Document::find($document->id);
        if ($documentModel) {
            return app(\App\Policies\DocumentPolicy::class)->view($user, $documentModel);
        }

        return false;
    }

    public function canAccessMaterialAttachment(User $user, File $file): bool
    {
        // Check if file belongs to material
        $attachment = DB::table('material_attachments')
            ->where('file_id', $file->id)
            ->first();

        if (!$attachment) {
            return false;
        }

        $material = DB::table('materials')->find($attachment->material_id);
        if (!$material) {
            return false;
        }

        // Use MaterialPolicy to check access
        $materialModel = \App\Models\Material::find($material->id);
        if ($materialModel) {
            return app(\App\Policies\MaterialPolicy::class)->view($user, $materialModel);
        }

        return false;
    }
}
