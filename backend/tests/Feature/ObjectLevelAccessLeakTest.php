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
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

/**
 * Тесты на утечки данных на уровне объектов
 * Проверяет что пользователи не могут получить доступ к данным других тенантов/пользователей
 */
class ObjectLevelAccessLeakTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected User $user3;
    protected Tenant $tenant1;
    protected Tenant $tenant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant1 = Tenant::factory()->create();
        $this->tenant2 = Tenant::factory()->create();

        $this->user1 = User::factory()->create(['tenant_id' => $this->tenant1->id]);
        $this->user2 = User::factory()->create(['tenant_id' => $this->tenant2->id]);
        $this->user3 = User::factory()->create(['tenant_id' => $this->tenant1->id]);
    }

    /**
     * Тест: список групп фильтруется по tenant_id
     */
    public function test_groups_list_filtered_by_tenant(): void
    {
        Group::factory()->count(5)->create(['tenant_id' => $this->tenant1->id]);
        Group::factory()->count(3)->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/groups');

        $response->assertStatus(200);
        $groups = $response->json('data') ?? $response->json();

        $this->assertCount(5, $groups);
        foreach ($groups as $group) {
            $this->assertEquals($this->tenant1->id, $group['tenant_id']);
            $this->assertNotEquals($this->tenant2->id, $group['tenant_id']);
        }
    }

    /**
     * Тест: прямой доступ к группе другого тенанта блокируется
     */
    public function test_direct_access_to_other_tenant_group_blocked(): void
    {
        $group1 = Group::factory()->create(['tenant_id' => $this->tenant1->id]);
        $group2 = Group::factory()->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/groups/' . $group2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: создание группы с подменой tenant_id
     */
    public function test_cannot_create_group_with_fake_tenant_id(): void
    {
        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/groups', [
                'name' => 'Hacked Group',
                'tenant_id' => $this->tenant2->id,
            ]);

        // Должен быть либо 403, либо tenant_id игнорируется и подставляется автоматически
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['tenant_id']);
        } else {
            // Если создание прошло, проверяем что tenant_id правильный
            $response->assertStatus(201);
            $group = Group::latest()->first();
            $this->assertEquals($this->tenant1->id, $group->tenant_id);
            $this->assertNotEquals($this->tenant2->id, $group->tenant_id);
        }
    }

    /**
     * Тест: обновление группы другого тенанта блокируется
     */
    public function test_cannot_update_other_tenant_group(): void
    {
        $group1 = Group::factory()->create(['tenant_id' => $this->tenant1->id]);
        $group2 = Group::factory()->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/groups/' . $group2->id, [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);

        // Проверяем что группа не изменилась
        $group2->refresh();
        $this->assertNotEquals('Hacked Name', $group2->name);
    }

    /**
     * Тест: удаление группы другого тенанта блокируется
     */
    public function test_cannot_delete_other_tenant_group(): void
    {
        $group1 = Group::factory()->create(['tenant_id' => $this->tenant1->id]);
        $group2 = Group::factory()->create(['tenant_id' => $this->tenant2->id]);
        $group2Id = $group2->id;

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson('/api/groups/' . $group2->id);

        $response->assertStatus(403);

        // Проверяем что группа не удалена
        $this->assertDatabaseHas('groups', ['id' => $group2Id]);
    }

    /**
     * Тест: доступ к урокам другого тенанта блокируется
     */
    public function test_cannot_access_other_tenant_lessons(): void
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

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/lessons/' . $lesson2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: доступ к оценкам другого тенанта блокируется
     */
    public function test_cannot_access_other_tenant_grades(): void
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

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/grades/' . $grade2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: доступ к журналу другого тенанта блокируется
     */
    public function test_cannot_access_other_tenant_journal(): void
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

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/journal/grid?lessonId=' . $lesson2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: сохранение в журнал другого тенанта блокируется
     */
    public function test_cannot_save_other_tenant_journal(): void
    {
        $scheduleItem2 = ScheduleItem::factory()->create(['tenant_id' => $this->tenant2->id]);

        $lesson2 = Lesson::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'schedule_item_id' => $scheduleItem2->id,
        ]);

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/journal/grid/save', [
                'lessonId' => $lesson2->id,
                'grades' => [],
                'attendance' => [],
            ]);

        $response->assertStatus(403);
    }

    /**
     * Тест: доступ к уведомлениям другого пользователя блокируется
     */
    public function test_cannot_access_other_user_notifications(): void
    {
        $notification1 = Notification::factory()->create(['user_id' => $this->user1->id]);
        $notification2 = Notification::factory()->create(['user_id' => $this->user2->id]);
        $notification3 = Notification::factory()->create(['user_id' => $this->user3->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Не должен видеть уведомления пользователя из другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/notifications/' . $notification2->id);

        $response->assertStatus(403);

        // Может видеть свои уведомления
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/notifications/' . $notification1->id);

        $response->assertStatus(200);
    }

    /**
     * Тест: список уведомлений фильтруется по user_id
     */
    public function test_notifications_list_filtered_by_user(): void
    {
        Notification::factory()->count(3)->create(['user_id' => $this->user1->id]);
        Notification::factory()->count(2)->create(['user_id' => $this->user2->id]);
        Notification::factory()->count(1)->create(['user_id' => $this->user3->id]);

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/notifications');

        $response->assertStatus(200);
        $notifications = $response->json('data') ?? $response->json();

        $this->assertCount(3, $notifications);
        foreach ($notifications as $notification) {
            $this->assertEquals($this->user1->id, $notification['user_id']);
        }
    }

    /**
     * Тест: доступ к документам другого тенанта блокируется
     */
    public function test_cannot_access_other_tenant_documents(): void
    {
        $document1 = Document::factory()->create(['created_by' => $this->user1->id]);
        $document2 = Document::factory()->create(['created_by' => $this->user2->id]);

        $token = JWTAuth::fromUser($this->user1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/documents/' . $document2->id);

        $response->assertStatus(403);
    }

    /**
     * Тест: SQL injection через параметры не работает
     */
    public function test_sql_injection_through_parameters_fails(): void
    {
        Group::factory()->count(3)->create(['tenant_id' => $this->tenant1->id]);
        Group::factory()->count(2)->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Попытка SQL injection
        $maliciousIds = [
            "1 OR 1=1",
            "1' OR '1'='1",
            "1; DROP TABLE groups; --",
            "1 UNION SELECT * FROM groups",
        ];

        foreach ($maliciousIds as $maliciousId) {
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->getJson('/api/groups/' . $maliciousId);

            // Должен вернуть 404 или 403, но не данные другого тенанта
            $this->assertContains($response->status(), [404, 403, 422]);
        }
    }

    /**
     * Тест: массовые операции не затрагивают данные другого тенанта
     */
    public function test_bulk_operations_respect_tenant_boundaries(): void
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

        // Попытка массового обновления с ID урока другого тенанта
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/journal/grid/save', [
                'lessonId' => $lesson2->id,
                'grades' => [
                    ['studentId' => 1, 'value' => 5],
                ],
                'attendance' => [],
            ]);

        $response->assertStatus(403);

        // Проверяем что данные не изменились
        $this->assertDatabaseMissing('grades', [
            'lesson_id' => $lesson2->id,
            'value' => 5,
        ]);
    }

    /**
     * Тест: прямой доступ через ID перечисления не раскрывает данные
     */
    public function test_sequential_id_enumeration_does_not_leak_data(): void
    {
        $group1 = Group::factory()->create(['tenant_id' => $this->tenant1->id]);
        $group2 = Group::factory()->create(['tenant_id' => $this->tenant2->id]);

        $token = JWTAuth::fromUser($this->user1);

        // Попытка получить доступ через следующий ID
        $nextId = $group1->id + 1;
        
        // Если nextId совпадает с group2->id, должен быть 403
        // Если нет, может быть 404
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/groups/' . $nextId);

        if ($nextId === $group2->id) {
            $response->assertStatus(403);
        } else {
            $response->assertStatus(404);
        }
    }
}


