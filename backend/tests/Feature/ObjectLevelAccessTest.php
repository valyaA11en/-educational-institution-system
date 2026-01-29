<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Grade;
use App\Models\Document;
use App\Models\ScheduleItem;
use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ObjectLevelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected Tenant $tenant1;
    protected Tenant $tenant2;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем два тенанта
        $this->tenant1 = Tenant::factory()->create();
        $this->tenant2 = Tenant::factory()->create();

        // Создаем пользователей для каждого тенанта
        $this->user1 = User::factory()->create(['tenant_id' => $this->tenant1->id]);
        $this->user2 = User::factory()->create(['tenant_id' => $this->tenant2->id]);
    }

    /**
     * Тест: пользователь не может получить доступ к группам другого тенанта
     */
    public function test_user_cannot_access_other_tenant_groups(): void
    {
        $group1 = Group::factory()->create(['tenant_id' => $this->tenant1->id]);
        $group2 = Group::factory()->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь должен видеть только свою группу
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/groups/' . $group1->id);

        $response->assertStatus(200);

        // Пользователь не должен видеть группу другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/groups/' . $group2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: пользователь не может получить доступ к урокам другого тенанта
     */
    public function test_user_cannot_access_other_tenant_lessons(): void
    {
        $scheduleItem1 = ScheduleItem::factory()->create(['tenant_id' => $this->tenant1->id]);
        $scheduleItem2 = ScheduleItem::factory()->create(['tenant_id' => $this->tenant2->id]);

        $lesson1 = Lesson::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'schedule_item_id' => $scheduleItem1->id,
        ]);

        $lesson2 = Lesson::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'schedule_item_id' => $scheduleItem2->id,
        ]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь должен видеть только свои уроки
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/lessons/' . $lesson1->id);

        $response->assertStatus(200);

        // Пользователь не должен видеть уроки другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/lessons/' . $lesson2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: пользователь не может получить доступ к оценкам другого тенанта
     */
    public function test_user_cannot_access_other_tenant_grades(): void
    {
        $scheduleItem1 = ScheduleItem::factory()->create(['tenant_id' => $this->tenant1->id]);
        $scheduleItem2 = ScheduleItem::factory()->create(['tenant_id' => $this->tenant2->id]);

        $lesson1 = Lesson::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'schedule_item_id' => $scheduleItem1->id,
        ]);

        $lesson2 = Lesson::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'schedule_item_id' => $scheduleItem2->id,
        ]);

        $grade1 = Grade::factory()->create(['lesson_id' => $lesson1->id]);
        $grade2 = Grade::factory()->create(['lesson_id' => $lesson2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь не должен видеть оценки другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/grades/' . $grade2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: пользователь не может получить доступ к документам другого тенанта
     */
    public function test_user_cannot_access_other_tenant_documents(): void
    {
        $document1 = Document::factory()->create(['created_by' => $this->user1->id]);
        $document2 = Document::factory()->create(['created_by' => $this->user2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь не должен видеть документы другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/documents/' . $document2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: пользователь не может получить доступ к уведомлениям другого пользователя
     */
    public function test_user_cannot_access_other_user_notifications(): void
    {
        $notification1 = Notification::factory()->create(['user_id' => $this->user1->id]);
        $notification2 = Notification::factory()->create(['user_id' => $this->user2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь должен видеть только свои уведомления
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/notifications/' . $notification1->id);

        $response->assertStatus(200);

        // Пользователь не должен видеть уведомления другого пользователя
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/notifications/' . $notification2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: пользователь не может создать ресурс с tenant_id другого тенанта
     */
    public function test_user_cannot_create_resource_with_other_tenant_id(): void
    {
        $token = JWTAuth::fromUser($this->user1);

        // Попытка создать группу с tenant_id другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/groups', [
                'name' => 'Test Group',
                'tenant_id' => $this->tenant2->id, // Другой тенант
            ]);

        // Должна быть валидация или автоматическая подстановка правильного tenant_id
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['tenant_id']);
        } else {
            // Если tenant_id игнорируется и подставляется автоматически, проверяем что создана группа с правильным tenant_id
            $response->assertStatus(201);
            $group = Group::latest()->first();
            $this->assertEquals($this->tenant1->id, $group->tenant_id);
        }
    }

    /**
     * Тест: пользователь не может обновить ресурс другого тенанта
     */
    public function test_user_cannot_update_other_tenant_resource(): void
    {
        $group1 = Group::factory()->create(['tenant_id' => $this->tenant1->id]);
        $group2 = Group::factory()->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь может обновить свою группу
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/groups/' . $group1->id, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);

        // Пользователь не может обновить группу другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/groups/' . $group2->id, [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Тест: пользователь не может удалить ресурс другого тенанта
     */
    public function test_user_cannot_delete_other_tenant_resource(): void
    {
        $group1 = Group::factory()->create(['tenant_id' => $this->tenant1->id]);
        $group2 = Group::factory()->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь может удалить свою группу
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson('/api/groups/' . $group1->id);

        $response->assertStatus(200);

        // Пользователь не может удалить группу другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson('/api/groups/' . $group2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: учитель может редактировать только свои уроки
     */
    public function test_teacher_can_only_edit_own_lessons(): void
    {
        // TODO: Реализовать после добавления ролей и проверки прав доступа
        $this->markTestSkipped('Requires role-based access control implementation');
    }

    /**
     * Тест: пользователь не может получить список ресурсов другого тенанта через фильтры
     */
    public function test_user_cannot_list_other_tenant_resources(): void
    {
        Group::factory()->count(3)->create(['tenant_id' => $this->tenant1->id]);
        Group::factory()->count(2)->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/groups');

        $response->assertStatus(200);
        
        // Проверяем что в списке только группы текущего тенанта
        $groups = $response->json('data') ?? $response->json();
        foreach ($groups as $group) {
            $this->assertEquals($this->tenant1->id, $group['tenant_id']);
        }
    }

    /**
     * Тест: прямой SQL injection через tenant_id не работает
     */
    public function test_sql_injection_through_tenant_id_fails(): void
    {
        $token = JWTAuth::fromUser($this->user1);

        // Попытка SQL injection через параметр
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/groups?tenant_id=1 OR 1=1');

        // Должно возвращать только данные текущего пользователя
        $response->assertStatus(200);
        $groups = $response->json('data') ?? $response->json();
        foreach ($groups as $group) {
            $this->assertEquals($this->tenant1->id, $group['tenant_id']);
        }
    }

    /**
     * Тест: пользователь не может получить доступ к журналу другого тенанта
     */
    public function test_user_cannot_access_other_tenant_journal(): void
    {
        $scheduleItem1 = ScheduleItem::factory()->create(['tenant_id' => $this->tenant1->id]);
        $scheduleItem2 = ScheduleItem::factory()->create(['tenant_id' => $this->tenant2->id]);

        $lesson1 = Lesson::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'schedule_item_id' => $scheduleItem1->id,
        ]);

        $lesson2 = Lesson::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'schedule_item_id' => $scheduleItem2->id,
        ]);

        $token = JWTAuth::fromUser($this->user1);

        // Пользователь должен видеть только свой журнал
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/journal/grid?lessonId=' . $lesson1->id);

        $response->assertStatus(200);

        // Пользователь не должен видеть журнал другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/journal/grid?lessonId=' . $lesson2->id);

        $response->assertStatus(403);
    }
}


