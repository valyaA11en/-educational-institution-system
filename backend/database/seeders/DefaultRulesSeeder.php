<?php

namespace Database\Seeders;

use App\Models\Rule;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Support\Events\EventTypes;

class DefaultRulesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->first();

        $createdBy = $admin?->id ?? 1;

        // Rule 1: Low average grade notification
        Rule::create([
            'name' => 'Низкая средняя оценка - уведомление куратору',
            'enabled' => true,
            'scope' => 'global',
            'conditions_json' => [
                'event_type' => EventTypes::GRADE_CREATED,
                'thresholds' => [
                    'avg' => [
                        'operator' => 'less_than_or_equal',
                        'value' => 2.5,
                        'period_days' => 30,
                    ],
                ],
            ],
            'actions_json' => [
                [
                    'type' => 'create_notification',
                    'to' => 'curator',
                    'notification_type' => 'grade.low_average',
                    'message' => 'Средняя оценка студента за последние 30 дней ниже 2.5',
                    'channel' => 'in_app',
                    'payload' => [],
                ],
            ],
            'created_by' => $createdBy,
        ]);

        // Rule 2: Assignment due soon notification
        Rule::create([
            'name' => 'Скоро дедлайн задания - уведомление студенту и родителю',
            'enabled' => true,
            'scope' => 'global',
            'conditions_json' => [
                'event_type' => EventTypes::ASSIGNMENT_DUE_SOON,
            ],
            'actions_json' => [
                [
                    'type' => 'create_notification',
                    'to' => 'student',
                    'notification_type' => 'assignment.due_soon',
                    'message' => 'Скоро дедлайн задания: {title}',
                    'channel' => 'in_app',
                    'payload' => [],
                ],
                [
                    'type' => 'create_notification',
                    'to' => 'parent',
                    'notification_type' => 'assignment.due_soon',
                    'message' => 'У вашего ребенка скоро дедлайн задания: {title}',
                    'channel' => 'in_app',
                    'payload' => [],
                ],
            ],
            'created_by' => $createdBy,
        ]);

        // Rule 3: Ticket created - notify admin
        Rule::create([
            'name' => 'Создан тикет - уведомление администратору',
            'enabled' => true,
            'scope' => 'global',
            'conditions_json' => [
                'event_type' => EventTypes::TICKET_CREATED,
            ],
            'actions_json' => [
                [
                    'type' => 'create_notification',
                    'to' => 'admin',
                    'notification_type' => 'ticket.created',
                    'message' => 'Создан новый тикет: {title}',
                    'channel' => 'in_app',
                    'payload' => [],
                ],
            ],
            'created_by' => $createdBy,
        ]);
    }
}


