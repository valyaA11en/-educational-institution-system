<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AddTenantTraitToModels extends Command
{
    protected $signature = 'tenant:add-trait';
    protected $description = 'Add HasTenant trait to models that need tenant_id';

    protected $models = [
        'Subgroup',
        'TimeSlot',
        'ScheduleVersion',
        'ScheduleReplacement',
        'DutyShift',
        'Lesson',
        'Grade',
        'GradeChange',
        'Attendance',
        'GradePeriodSummary',
        'Material',
        'MaterialTarget',
        'MaterialRead',
        'Assignment',
        'AssignmentTarget',
        'Submission',
        'SubmissionFile',
        'Document',
        'DocTemplate',
        'DocumentRoute',
        'DocumentAck',
        'DocumentRegistry',
        'Notification',
        'NotificationSetting',
        'ChatThread',
        'ChatMember',
        'ChatMessage',
        'ChatReport',
        'ChatThreadSettings',
        'Ticket',
        'TicketMessage',
        'Rule',
        'Risk',
        'WebhookEndpoint',
        'WebhookDelivery',
        'Setting',
        'File',
    ];

    public function handle(): void
    {
        $modelsPath = app_path('Models');
        $traitAdded = 0;

        foreach ($this->models as $modelName) {
            $filePath = $modelsPath . '/' . $modelName . '.php';
            
            if (!File::exists($filePath)) {
                $this->warn("Model {$modelName} not found, skipping...");
                continue;
            }

            $content = File::get($filePath);

            // Skip if already has trait
            if (str_contains($content, 'HasTenant')) {
                $this->info("Model {$modelName} already has HasTenant trait");
                continue;
            }

            // Add use statement if not exists
            if (!str_contains($content, 'use App\Traits\HasTenant;')) {
                // Find the last use statement
                if (preg_match('/(use [^;]+;[\r\n]+)+/', $content, $matches)) {
                    $content = str_replace(
                        $matches[0],
                        $matches[0] . "use App\Traits\HasTenant;\n",
                        $content
                    );
                } else {
                    // Add after namespace
                    $content = preg_replace(
                        '/(namespace [^;]+;[\r\n]+)/',
                        "$1use App\Traits\HasTenant;\n",
                        $content
                    );
                }
            }

            // Add trait to class
            if (preg_match('/class\s+' . $modelName . '\s+extends\s+Model\s*\{[\r\n]+\s*use\s+([^;]+);/', $content, $matches)) {
                $existingTraits = $matches[1];
                if (!str_contains($existingTraits, 'HasTenant')) {
                    $content = str_replace(
                        "use {$existingTraits};",
                        "use {$existingTraits}, HasTenant;",
                        $content
                    );
                }
            } elseif (preg_match('/class\s+' . $modelName . '\s+extends\s+Model\s*\{/', $content)) {
                $content = preg_replace(
                    '/(class\s+' . $modelName . '\s+extends\s+Model\s*\{)/',
                    "$1\n    use HasFactory, HasTenant;",
                    $content
                );
            }

            // Add tenant_id to fillable if exists
            if (str_contains($content, 'protected $fillable')) {
                if (!str_contains($content, "'tenant_id'") && !str_contains($content, '"tenant_id"')) {
                    $content = preg_replace(
                        '/(protected \$fillable = \[[\r\n]+)/',
                        "$1        'tenant_id',\n",
                        $content
                    );
                }
            }

            File::put($filePath, $content);
            $this->info("Added HasTenant trait to {$modelName}");
            $traitAdded++;
        }

        $this->info("Done! Added trait to {$traitAdded} models.");
    }
}


