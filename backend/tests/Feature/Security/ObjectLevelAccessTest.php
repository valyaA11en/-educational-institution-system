<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Grade;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ObjectLevelAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тест: студент не может видеть оценки других студентов
     */
    public function test_student_cannot_view_other_student_grades(): void
    {
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        // TODO: Создать оценки для student2
        // TODO: Авторизоваться как student1
        // TODO: Попытаться получить оценки student2
        // TODO: Проверить 403/404
    }

    /**
     * Тест: куратор видит только студентов своей группы
     */
    public function test_curator_can_only_view_their_group_students(): void
    {
        // TODO: Реализовать
    }

    /**
     * Тест: учитель видит только свои уроки
     */
    public function test_teacher_can_only_view_their_lessons(): void
    {
        // TODO: Реализовать
    }
}


