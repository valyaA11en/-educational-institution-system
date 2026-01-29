<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TopicPerformanceStat;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\CurriculumTopic;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TopicPerformanceCommand extends Command
{
    protected $signature = 'analytics:topic-performance 
                            {--term-id= : Term ID to calculate stats for}
                            {--subject-id= : Subject ID to calculate stats for}
                            {--force : Force recalculation}';

    protected $description = 'Calculate topic performance statistics (avg grades, fail percent)';

    public function handle(): int
    {
        $termId = $this->option('term-id');
        $subjectId = $this->option('subject-id');
        $force = $this->option('force');

        $this->info('Calculating topic performance statistics...');

        // Получаем все темы (фильтр по subject будет применяться позже через оценки)
        $topics = CurriculumTopic::all();

        $bar = $this->output->createProgressBar($topics->count());
        $bar->start();

        $statsCreated = 0;
        $statsUpdated = 0;

        foreach ($topics as $topic) {
            $this->calculateTopicStats($topic, $termId, $force, $statsCreated, $statsUpdated);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Stats created: {$statsCreated}, updated: {$statsUpdated}");

        return Command::SUCCESS;
    }

    protected function calculateTopicStats(
        CurriculumTopic $topic,
        ?int $termId,
        bool $force,
        int &$statsCreated,
        int &$statsUpdated
    ): void {
        $tenantId = app('tenant_id');
        
        // Получаем оценки по теме через уроки
        $gradesQuery = Grade::query()
            ->join('lessons', 'grades.lesson_id', '=', 'lessons.id')
            ->join('schedule_items', 'lessons.schedule_item_id', '=', 'schedule_items.id')
            ->where('lessons.ktp_topic_id', $topic->id)
            ->where('schedule_items.tenant_id', $tenantId)
            ->whereNotNull('grades.student_user_id')
            ->select([
                'grades.student_user_id',
                'grades.value',
                'schedule_items.subject_id',
            ]);

        // Добавляем join для получения term_id
        $gradesQuery->leftJoin('schedule_versions', 'schedule_items.version_id', '=', 'schedule_versions.id')
            ->addSelect(DB::raw('schedule_versions.term_id'));

        // Фильтр по периоду (term)
        if ($termId) {
            $gradesQuery->where('schedule_versions.term_id', $termId);
        }
        
        // Фильтр по subject_id
        if ($subjectId) {
            $gradesQuery->where('schedule_items.subject_id', $subjectId);
        }

        $grades = $gradesQuery->get();

        if ($grades->isEmpty()) {
            return;
        }

        // Получаем subject_id из первой оценки
        $firstGrade = $grades->first();
        if (!$firstGrade) {
            return;
        }
        
        $subjectId = $firstGrade->subject_id ?? null;
        if (!$subjectId) {
            return;
        }
        
        // Получаем term_id, если есть
        $calculatedTermId = $firstGrade->term_id ?? $termId;

        // Группируем оценки по студентам и считаем средний балл
        $studentGrades = $grades->groupBy('student_user_id');
        $studentsTotal = $studentGrades->count();
        $studentsFailed = 0;

        foreach ($studentGrades as $studentId => $studentGradeList) {
            $avg = $studentGradeList->avg('value');
            if ($avg <= 2.0) {
                $studentsFailed++;
            }
        }

        $failPercent = $studentsTotal > 0 ? ($studentsFailed / $studentsTotal) * 100 : 0;

        // Сохраняем или обновляем статистику
        $stat = TopicPerformanceStat::firstOrNew([
            'tenant_id' => $tenantId,
            'subject_id' => $subjectId,
            'ktp_topic_id' => $topic->id,
            'term_id' => $calculatedTermId,
        ]);

        $isNew = !$stat->exists;

        $stat->students_total = $studentsTotal;
        $stat->students_failed = $studentsFailed;
        $stat->fail_percent = round($failPercent, 2);
        $stat->calculated_at = Carbon::now();
        $stat->save();

        if ($isNew) {
            $statsCreated++;
        } else {
            $statsUpdated++;
        }
    }
}

