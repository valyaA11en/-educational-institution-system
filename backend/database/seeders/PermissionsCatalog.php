<?php

namespace Database\Seeders;

class PermissionsCatalog
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            // Auth
            'auth.manage_sessions' => 'Управление сессиями пользователей',

            // Users & Roles
            'users.read' => 'Просмотр пользователей',
            'users.create' => 'Создание пользователей',
            'users.update' => 'Редактирование пользователей',
            'users.delete' => 'Удаление пользователей',
            'roles.assign' => 'Назначение ролей пользователям',

            // Directory
            'directory.manage' => 'Управление справочниками (группы, подгруппы, предметы, кабинеты, слоты)',

            // Schedule
            'schedule.view' => 'Просмотр расписания',
            'schedule.edit' => 'Редактирование расписания',
            'schedule.publish' => 'Публикация версий расписания',
            'schedule.force_override' => 'Игнорирование конфликтов и принудительное сохранение расписания',
            'schedule.replacements.manage' => 'Управление заменами в расписании',
            'schedule.duties.manage' => 'Управление дежурствами',

            // Journal
            'journal.view' => 'Просмотр электронного журнала',
            'journal.grade.create' => 'Выставление оценок',
            'journal.grade.edit' => 'Редактирование выставленных оценок',
            'journal.attendance.edit' => 'Отметка посещаемости',
            'journal.reports.view' => 'Просмотр отчетов по журналу',

            // Assignments
            'assignments.view' => 'Просмотр заданий',
            'assignments.create' => 'Создание заданий',
            'assignments.submit' => 'Сдача заданий',
            'assignments.grade' => 'Оценивание заданий',

            // Materials
            'materials.view' => 'Просмотр материалов',
            'materials.create' => 'Создание и редактирование материалов',
            'materials.readmark' => 'Отметка материалов как прочитанных',

            // Documents
            'documents.view' => 'Просмотр документов',
            'documents.create' => 'Создание документов',
            'documents.approve' => 'Согласование документов',
            'documents.sign' => 'Подписание документов',
            'documents.export' => 'Экспорт документов',
            'documents.registry' => 'Работа с регистрационной книгой',

            // Notifications
            'notifications.view' => 'Просмотр уведомлений',
            'notifications.manage' => 'Управление настройками уведомлений',

            // Chat
            'chat.view' => 'Просмотр чатов',
            'chat.write' => 'Отправка сообщений в чат',
            'chat.moderate' => 'Модерация чатов',

            // Contests
            'contests.view' => 'Просмотр конкурсов',
            'contests.manage' => 'Управление конкурсами',
            'contests.jury' => 'Работа жюри конкурсов',

            // Tickets
            'tickets.create' => 'Создание обращений (тикетов)',
            'tickets.manage' => 'Управление обращениями',

            // Rules & Analytics & Audit
            'rules.manage' => 'Управление правилами автоматизации',
            'analytics.view' => 'Просмотр аналитических отчетов',
            'audit.view' => 'Просмотр журнала аудита',
        ];
    }
}


