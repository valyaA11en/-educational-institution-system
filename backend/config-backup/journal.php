<?php

return [
    /**
     * Максимальное количество дней для редактирования оценки без причины
     */
    'max_edit_days' => env('JOURNAL_MAX_EDIT_DAYS', 7),

    /**
     * Требуется ли причина для редактирования старых оценок
     */
    'require_reason_for_old_grade' => env('JOURNAL_REQUIRE_REASON_FOR_OLD_GRADE', true),
];


