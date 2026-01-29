<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentTarget;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Contest;
use App\Models\ContestTarget;
use App\Models\Document;
use App\Models\DocTemplate;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\MaterialTarget;
use App\Models\Notification;
use App\Models\Risk;
use App\Models\Room;
use App\Models\Rule;
use App\Models\ScheduleItem;
use App\Models\ScheduleVersion;
use App\Models\Subgroup;
use App\Models\Subject;
use App\Models\Ticket;
use App\Models\TicketSLA;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\UserLinkParentChild;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FullDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = \App\Models\Tenant::where('slug', 'demo')->first();
        if (!$tenant) {
            return;
        }

        app()->instance('tenant_id', $tenant->id);

        DB::transaction(function () use ($tenant): void {
            // 1. Academic Year + Terms
            $currentYear = date('Y');
            $academicYearId = DB::table('academic_years')->insertGetId([
                'name' => $currentYear . '-' . ($currentYear + 1),
                'start_date' => $currentYear . '-09-01',
                'end_date' => ($currentYear + 1) . '-08-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $fallTermId = DB::table('terms')->insertGetId([
                'academic_year_id' => $academicYearId,
                'name' => 'Осенний семестр',
                'start_date' => $currentYear . '-09-01',
                'end_date' => $currentYear . '-12-31',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $springTermId = DB::table('terms')->insertGetId([
                'academic_year_id' => $academicYearId,
                'name' => 'Весенний семестр',
                'start_date' => ($currentYear + 1) . '-01-10',
                'end_date' => ($currentYear + 1) . '-06-30',
                'is_current' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Subjects
            $subjects = [
                ['code' => 'MATH', 'name' => 'Математика'],
                ['code' => 'RUS', 'name' => 'Русский язык'],
                ['code' => 'ENG', 'name' => 'Английский язык'],
                ['code' => 'INF', 'name' => 'Информатика'],
                ['code' => 'HIST', 'name' => 'История'],
                ['code' => 'PHYS', 'name' => 'Физика'],
                ['code' => 'CHEM', 'name' => 'Химия'],
                ['code' => 'BIO', 'name' => 'Биология'],
                ['code' => 'LIT', 'name' => 'Литература'],
                ['code' => 'GEOG', 'name' => 'География'],
            ];

            $subjectModels = [];
            foreach ($subjects as $subjectData) {
                $subjectModels[$subjectData['code']] = Subject::firstOrCreate(
                    ['code' => $subjectData['code']],
                    ['name' => $subjectData['name']]
                );
            }

            // 3. Groups & Subgroups
            $groups = [
                ['code' => '1IS-11', 'name' => '1ИС-11'],
                ['code' => '1IS-12', 'name' => '1ИС-12'],
                ['code' => '2IS-31', 'name' => '2ИС-31'],
                ['code' => '2IS-32', 'name' => '2ИС-32'],
                ['code' => '3IS-41', 'name' => '3ИС-41'],
                ['code' => '1PR-11', 'name' => '1ПР-11'],
                ['code' => '1PR-12', 'name' => '1ПР-12'],
                ['code' => '2PR-21', 'name' => '2ПР-21'],
            ];

            $groupModels = [];
            foreach ($groups as $groupData) {
                $groupModels[$groupData['code']] = Group::firstOrCreate(
                    ['code' => $groupData['code']],
                    ['name' => $groupData['name']]
                );
            }

            // Create subgroups for each group
            $subgroupModels = [];
            foreach ($groupModels as $code => $group) {
                $subgroupModels[$code . '-A'] = Subgroup::firstOrCreate(
                    ['code' => $code . '-A'],
                    ['group_id' => $group->id, 'name' => $code . '-A']
                );
                $subgroupModels[$code . '-B'] = Subgroup::firstOrCreate(
                    ['code' => $code . '-B'],
                    ['group_id' => $group->id, 'name' => $code . '-B']
                );
            }

            // 4. Time Slots
            $slotTimes = [
                ['start' => '08:00', 'end' => '09:30'],
                ['start' => '09:45', 'end' => '11:15'],
                ['start' => '11:30', 'end' => '13:00'],
                ['start' => '13:30', 'end' => '15:00'],
                ['start' => '15:15', 'end' => '16:45'],
                ['start' => '17:00', 'end' => '18:30'],
                ['start' => '18:45', 'end' => '20:15'],
                ['start' => '20:30', 'end' => '22:00'],
            ];

            $slots = [];
            for ($i = 1; $i <= 8; $i++) {
                $slots[$i] = TimeSlot::firstOrCreate(
                    ['name' => (string) $i],
                    [
                        'start_time' => $slotTimes[$i - 1]['start'],
                        'end_time' => $slotTimes[$i - 1]['end'],
                        'order' => $i,
                    ]
                );
            }

            // 5. Rooms
            $rooms = [
                ['code' => '101', 'name' => 'Аудитория 101', 'capacity' => 30, 'attributes' => []],
                ['code' => '102', 'name' => 'Аудитория 102', 'capacity' => 25, 'attributes' => []],
                ['code' => '201', 'name' => 'Компьютерный класс 201', 'capacity' => 15, 'attributes' => ['pc' => true]],
                ['code' => '202', 'name' => 'Компьютерный класс 202', 'capacity' => 20, 'attributes' => ['pc' => true]],
                ['code' => '301', 'name' => 'Лаборатория 301', 'capacity' => 16, 'attributes' => ['lab' => true]],
                ['code' => '302', 'name' => 'Лаборатория 302', 'capacity' => 18, 'attributes' => ['lab' => true]],
                ['code' => 'ACT', 'name' => 'Актовый зал', 'capacity' => 200, 'attributes' => ['auditorium' => true]],
                ['code' => 'GYM', 'name' => 'Спортивный зал', 'capacity' => 30, 'attributes' => ['gym' => true]],
            ];

            $roomModels = [];
            foreach ($rooms as $roomData) {
                $roomModels[$roomData['code']] = Room::firstOrCreate(
                    ['code' => $roomData['code']],
                    [
                        'name' => $roomData['name'],
                        'capacity' => $roomData['capacity'],
                        'attributes' => $roomData['attributes'],
                    ]
                );
            }

            // 6. Users (много пользователей)
            $password = Hash::make('Password123!');

            // Administrators
            $admin = User::firstOrCreate(
                ['email' => 'admin@college.edu'],
                ['fio' => 'Главный администратор', 'password_hash' => $password, 'status' => 'active']
            );
            $admin->roles()->sync([DB::table('roles')->where('name', 'admin')->value('id')]);

            $director = User::firstOrCreate(
                ['email' => 'director@college.edu'],
                ['fio' => 'Петров Петр Петрович', 'password_hash' => $password, 'status' => 'active']
            );
            $director->roles()->sync([DB::table('roles')->where('name', 'management')->value('id')]);

            $methodist = User::firstOrCreate(
                ['email' => 'methodist@college.edu'],
                ['fio' => 'Сидорова Анна Викторовна', 'password_hash' => $password, 'status' => 'active']
            );
            $methodist->roles()->sync([DB::table('roles')->where('name', 'methodist')->value('id')]);

            $scheduler = User::firstOrCreate(
                ['email' => 'scheduler@college.edu'],
                ['fio' => 'Кузнецов Алексей Иванович', 'password_hash' => $password, 'status' => 'active']
            );
            $scheduler->roles()->sync([DB::table('roles')->where('name', 'scheduler')->value('id')]);

            // Teachers
            $teachers = [
                ['email' => 'math.teacher@college.edu', 'fio' => 'Иванова Мария Александровна', 'subjects' => ['MATH', 'PHYS']],
                ['email' => 'cs.teacher@college.edu', 'fio' => 'Смирнов Дмитрий Сергеевич', 'subjects' => ['INF']],
                ['email' => 'lang.teacher@college.edu', 'fio' => 'Козлова Елена Николаевна', 'subjects' => ['RUS', 'LIT']],
                ['email' => 'eng.teacher@college.edu', 'fio' => 'Johnson Mark', 'subjects' => ['ENG']],
                ['email' => 'history.teacher@college.edu', 'fio' => 'Михайлов Сергей Владимирович', 'subjects' => ['HIST']],
                ['email' => 'science.teacher@college.edu', 'fio' => 'Федорова Татьяна Игоревна', 'subjects' => ['CHEM', 'BIO']],
            ];

            $teacherModels = [];
            foreach ($teachers as $teacherData) {
                $teacher = User::firstOrCreate(
                    ['email' => $teacherData['email']],
                    ['fio' => $teacherData['fio'], 'password_hash' => $password, 'status' => 'active']
                );
                $teacher->roles()->sync([DB::table('roles')->where('name', 'teacher')->value('id')]);
                $teacherModels[$teacherData['email']] = $teacher;
            }

            // Curators
            $curators = [
                ['email' => 'curator1@college.edu', 'fio' => 'Романова Ольга Петровна', 'groups' => ['1IS-11', '1IS-12']],
                ['email' => 'curator2@college.edu', 'fio' => 'Васильев Игорь Николаевич', 'groups' => ['2IS-31', '2IS-32']],
                ['email' => 'curator3@college.edu', 'fio' => 'Новикова Светлана Юрьевна', 'groups' => ['1PR-11', '1PR-12']],
            ];

            $curatorModels = [];
            foreach ($curators as $curatorData) {
                $curator = User::firstOrCreate(
                    ['email' => $curatorData['email']],
                    ['fio' => $curatorData['fio'], 'password_hash' => $password, 'status' => 'active']
                );
                $curator->roles()->sync([DB::table('roles')->where('name', 'curator')->value('id')]);
                $curatorModels[$curatorData['email']] = $curator;
            }

            // Students
            $students = [
                // 1IS-11
                ['email' => 'student1@college.edu', 'fio' => 'Алексеев Иван Петрович', 'group' => '1IS-11'],
                ['email' => 'student2@college.edu', 'fio' => 'Борисова Анна Сергеевна', 'group' => '1IS-11'],
                ['email' => 'student3@college.edu', 'fio' => 'Волков Дмитрий Александрович', 'group' => '1IS-11'],
                ['email' => 'student4@college.edu', 'fio' => 'Григорьева Елена Викторовна', 'group' => '1IS-11'],
                
                // 2IS-31
                ['email' => 'student5@college.edu', 'fio' => 'Данилов Максим Игоревич', 'group' => '2IS-31'],
                ['email' => 'student6@college.edu', 'fio' => 'Егорова Полина Андреевна', 'group' => '2IS-31'],
                ['email' => 'student7@college.edu', 'fio' => 'Жуков Артем Николаевич', 'group' => '2IS-31'],
                ['email' => 'student8@college.edu', 'fio' => 'Зайцева Мария Олеговна', 'group' => '2IS-31'],
                
                // 1PR-11
                ['email' => 'student9@college.edu', 'fio' => 'Иванов Владислав Юрьевич', 'group' => '1PR-11'],
                ['email' => 'student10@college.edu', 'fio' => 'Козлов Никита Дмитриевич', 'group' => '1PR-11'],
            ];

            $studentModels = [];
            foreach ($students as $studentData) {
                $student = User::firstOrCreate(
                    ['email' => $studentData['email']],
                    ['fio' => $studentData['fio'], 'password_hash' => $password, 'status' => 'active']
                );
                $student->roles()->sync([DB::table('roles')->where('name', 'student')->value('id')]);
                $studentModels[$studentData['email']] = $student;
                
                // Add to group
                DB::table('group_members')->insertOrIgnore([
                    'group_id' => $groupModels[$studentData['group']]->id,
                    'user_id' => $student->id,
                    'role_in_group' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Parents
            $parents = [
                ['email' => 'parent1@college.edu', 'fio' => 'Алексеева Светлана Викторовна', 'child' => 'student1@college.edu'],
                ['email' => 'parent2@college.edu', 'fio' => 'Борисов Сергей Николаевич', 'child' => 'student2@college.edu'],
                ['email' => 'parent3@college.edu', 'fio' => 'Данилова Ирина Петровна', 'child' => 'student5@college.edu'],
            ];

            $parentModels = [];
            foreach ($parents as $parentData) {
                $parent = User::firstOrCreate(
                    ['email' => $parentData['email']],
                    ['fio' => $parentData['fio'], 'password_hash' => $password, 'status' => 'active']
                );
                $parent->roles()->sync([DB::table('roles')->where('name', 'parent')->value('id')]);
                $parentModels[$parentData['email']] = $parent;

                // Create parent-child link
                UserLinkParentChild::firstOrCreate([
                    'parent_user_id' => $parent->id,
                    'student_user_id' => $studentModels[$parentData['child']]->id,
                ], [
                    'status' => 'approved',
                    'link_code_hash' => Hash::make('link-' . $parent->id),
                ]);
            }

            // 7. Add curators to groups
            foreach ($curators as $curatorData) {
                $curator = $curatorModels[$curatorData['email']];
                foreach ($curatorData['groups'] as $groupCode) {
                    DB::table('group_members')->insertOrIgnore([
                        'group_id' => $groupModels[$groupCode]->id,
                        'user_id' => $curator->id,
                        'role_in_group' => 'curator',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 8. Teacher-Subject-Group assignments
            foreach ($teachers as $teacherData) {
                $teacher = $teacherModels[$teacherData['email']];
                foreach ($teacherData['subjects'] as $subjectCode) {
                    // Assign to some groups
                    $assignedGroups = ['1IS-11', '2IS-31', '1PR-11']; // Some examples
                    foreach ($assignedGroups as $groupCode) {
                        if (isset($groupModels[$groupCode])) {
                            DB::table('teacher_subject_group')->insertOrIgnore([
                                'teacher_user_id' => $teacher->id,
                                'subject_id' => $subjectModels[$subjectCode]->id,
                                'group_id' => $groupModels[$groupCode]->id,
                                'subgroup_id' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // 9. Schedule Versions with full week schedule
            $scheduleVersion = ScheduleVersion::create([
                'term_id' => $fallTermId,
                'status' => 'published',
                'created_by' => $scheduler->id,
                'published_at' => now()->subWeek(),
            ]);

            // Create a full week schedule
            $today = now()->startOfWeek();
            $scheduleItems = [
                // Monday
                ['day' => 0, 'slot' => 1, 'group' => '1IS-11', 'subject' => 'MATH', 'teacher' => 'math.teacher@college.edu', 'room' => '101'],
                ['day' => 0, 'slot' => 2, 'group' => '1IS-11', 'subject' => 'INF', 'teacher' => 'cs.teacher@college.edu', 'room' => '201'],
                ['day' => 0, 'slot' => 3, 'group' => '2IS-31', 'subject' => 'ENG', 'teacher' => 'eng.teacher@college.edu', 'room' => '102'],
                
                // Tuesday
                ['day' => 1, 'slot' => 1, 'group' => '2IS-31', 'subject' => 'MATH', 'teacher' => 'math.teacher@college.edu', 'room' => '101'],
                ['day' => 1, 'slot' => 2, 'group' => '1PR-11', 'subject' => 'RUS', 'teacher' => 'lang.teacher@college.edu', 'room' => '102'],
                
                // Wednesday
                ['day' => 2, 'slot' => 1, 'group' => '1IS-11', 'subject' => 'HIST', 'teacher' => 'history.teacher@college.edu', 'room' => '101'],
                ['day' => 2, 'slot' => 2, 'group' => '2IS-31', 'subject' => 'INF', 'teacher' => 'cs.teacher@college.edu', 'room' => '201'],
                
                // Thursday
                ['day' => 3, 'slot' => 1, 'group' => '1PR-11', 'subject' => 'MATH', 'teacher' => 'math.teacher@college.edu', 'room' => '101'],
                ['day' => 3, 'slot' => 2, 'group' => '1IS-11', 'subject' => 'ENG', 'teacher' => 'eng.teacher@college.edu', 'room' => '102'],
                
                // Friday
                ['day' => 4, 'slot' => 1, 'group' => '2IS-31', 'subject' => 'PHYS', 'teacher' => 'math.teacher@college.edu', 'room' => '301'],
                ['day' => 4, 'slot' => 2, 'group' => '1PR-11', 'subject' => 'CHEM', 'teacher' => 'science.teacher@college.edu', 'room' => '302'],
            ];

            foreach ($scheduleItems as $item) {
                ScheduleItem::create([
                    'version_id' => $scheduleVersion->id,
                    'date' => $today->copy()->addDays($item['day']),
                    'time_slot_id' => $slots[$item['slot']]->id,
                    'group_id' => $groupModels[$item['group']]->id,
                    'subgroup_id' => null,
                    'subject_id' => $subjectModels[$item['subject']]->id,
                    'teacher_user_id' => $teacherModels[$item['teacher']]->id,
                    'room_id' => $roomModels[$item['room']]->id,
                    'created_by' => $scheduler->id,
                ]);
            }

            // 10. Lessons with Grades
            $mathTeacher = $teacherModels['math.teacher@college.edu'];
            $csTeacher = $teacherModels['cs.teacher@college.edu'];

            $lesson1 = Lesson::create([
                'schedule_item_id' => ScheduleItem::where('subject_id', $subjectModels['MATH']->id)->first()->id,
                'teacher_user_id' => $mathTeacher->id,
                'topic' => 'Линейные уравнения',
                'homework' => 'Решить задачи 15-20 на стр. 45',
                'lesson_date' => now()->subDays(3),
                'lesson_number' => 1,
                'status' => 'completed',
            ]);

            $lesson2 = Lesson::create([
                'schedule_item_id' => ScheduleItem::where('subject_id', $subjectModels['INF']->id)->first()->id,
                'teacher_user_id' => $csTeacher->id,
                'topic' => 'Основы программирования',
                'homework' => 'Написать программу Hello World',
                'lesson_date' => now()->subDays(2),
                'lesson_number' => 1,
                'status' => 'completed',
            ]);

            // Add grades for students
            $studentGrades = [
                ['student' => 'student1@college.edu', 'lesson' => $lesson1->id, 'grade' => 5, 'comment' => 'Отлично'],
                ['student' => 'student2@college.edu', 'lesson' => $lesson1->id, 'grade' => 4, 'comment' => 'Хорошо'],
                ['student' => 'student3@college.edu', 'lesson' => $lesson1->id, 'grade' => 3, 'comment' => 'Удовлетворительно'],
                ['student' => 'student5@college.edu', 'lesson' => $lesson2->id, 'grade' => 5, 'comment' => 'Отлично выполнено'],
                ['student' => 'student6@college.edu', 'lesson' => $lesson2->id, 'grade' => 4, 'comment' => 'Хорошая работа'],
            ];

            foreach ($studentGrades as $gradeData) {
                Grade::create([
                    'lesson_id' => $gradeData['lesson'],
                    'student_user_id' => $studentModels[$gradeData['student']]->id,
                    'grade_value' => $gradeData['grade'],
                    'grade_type' => 'lesson',
                    'comment' => $gradeData['comment'],
                    'given_by' => $gradeData['lesson'] == $lesson1->id ? $mathTeacher->id : $csTeacher->id,
                    'given_at' => now()->subDays(rand(1, 3)),
                ]);
            }

            // 11. Assignments
            $assignment1 = Assignment::create([
                'subject_id' => $subjectModels['MATH']->id,
                'teacher_user_id' => $mathTeacher->id,
                'title' => 'Контрольная работа: Алгебраические уравнения',
                'description' => 'Решить систему уравнений и найти корни квадратного уравнения',
                'due_at' => now()->addDays(7),
                'max_attempts' => 2,
                'visibility_scope' => 'group',
            ]);

            AssignmentTarget::create([
                'assignment_id' => $assignment1->id,
                'group_id' => $groupModels['1IS-11']->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            AssignmentTarget::create([
                'assignment_id' => $assignment1->id,
                'group_id' => $groupModels['2IS-31']->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            $assignment2 = Assignment::create([
                'subject_id' => $subjectModels['INF']->id,
                'teacher_user_id' => $csTeacher->id,
                'title' => 'Проект: Создание веб-страницы',
                'description' => 'Создать HTML страницу с CSS стилизацией по заданной теме',
                'due_at' => now()->addDays(14),
                'max_attempts' => 3,
                'visibility_scope' => 'group',
            ]);

            AssignmentTarget::create([
                'assignment_id' => $assignment2->id,
                'group_id' => $groupModels['1IS-11']->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            // 12. Materials
            $material1 = Material::create([
                'subject_id' => $subjectModels['MATH']->id,
                'title' => 'Лекция: Системы линейных уравнений',
                'content' => 'Подробное объяснение методов решения систем линейных уравнений: метод подстановки, метод сложения, графический метод.',
                'visibility_scope' => 'group',
                'created_by' => $mathTeacher->id,
            ]);

            MaterialTarget::create([
                'material_id' => $material1->id,
                'group_id' => $groupModels['1IS-11']->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            $material2 = Material::create([
                'subject_id' => $subjectModels['INF']->id,
                'title' => 'Презентация: HTML и CSS основы',
                'content' => 'Введение в веб-разработку. Структура HTML документа. Базовые CSS свойства для стилизации.',
                'visibility_scope' => 'group',
                'created_by' => $csTeacher->id,
            ]);

            MaterialTarget::create([
                'material_id' => $material2->id,
                'group_id' => $groupModels['1IS-11']->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            // 13. Chat Threads
            $groupChat = ChatThread::create([
                'type' => 'group',
                'group_id' => $groupModels['1IS-11']->id,
                'subject_id' => null,
                'created_by' => $curatorModels['curator1@college.edu']->id,
            ]);

            // Add chat members
            $chatMembers = [
                ['user' => $curatorModels['curator1@college.edu']->id, 'role' => 'moderator'],
                ['user' => $studentModels['student1@college.edu']->id, 'role' => 'member'],
                ['user' => $studentModels['student2@college.edu']->id, 'role' => 'member'],
                ['user' => $studentModels['student3@college.edu']->id, 'role' => 'member'],
                ['user' => $studentModels['student4@college.edu']->id, 'role' => 'member'],
            ];

            foreach ($chatMembers as $memberData) {
                ChatMember::create([
                    'thread_id' => $groupChat->id,
                    'user_id' => $memberData['user'],
                    'role_in_chat' => $memberData['role'],
                ]);
            }

            // Chat messages
            $messages = [
                ['user' => $curatorModels['curator1@college.edu']->id, 'text' => 'Добро пожаловать в чат группы 1ИС-11! Здесь мы будем обсуждать учебные вопросы.'],
                ['user' => $studentModels['student1@college.edu']->id, 'text' => 'Здравствуйте! Когда будет контрольная по математике?'],
                ['user' => $curatorModels['curator1@college.edu']->id, 'text' => 'Контрольная запланирована на следующую неделю. Детали уточните у преподавателя.'],
                ['user' => $studentModels['student2@college.edu']->id, 'text' => 'Спасибо за информацию!'],
                ['user' => $studentModels['student3@college.edu']->id, 'text' => 'А где можно найти материалы для подготовки?'],
            ];

            foreach ($messages as $messageData) {
                ChatMessage::create([
                    'thread_id' => $groupChat->id,
                    'user_id' => $messageData['user'],
                    'text' => $messageData['text'],
                    'created_at' => now()->subHours(rand(1, 72)),
                ]);
            }

            // 14. SLA Configuration
            $slaConfigs = [
                ['name' => 'Bronze SLA', 'level' => 'bronze', 'response_time_hours' => 48, 'resolution_time_hours' => 168],
                ['name' => 'Silver SLA', 'level' => 'silver', 'response_time_hours' => 24, 'resolution_time_hours' => 72],
                ['name' => 'Gold SLA', 'level' => 'gold', 'response_time_hours' => 8, 'resolution_time_hours' => 24],
                ['name' => 'Platinum SLA', 'level' => 'platinum', 'response_time_hours' => 4, 'resolution_time_hours' => 12],
            ];

            foreach ($slaConfigs as $slaConfig) {
                TicketSLA::firstOrCreate(
                    ['level' => $slaConfig['level']],
                    [
                        'name' => $slaConfig['name'],
                        'category' => 'general',
                        'priority' => 'normal',
                        'response_time_hours' => $slaConfig['response_time_hours'],
                        'resolution_time_hours' => $slaConfig['resolution_time_hours'],
                        'escalation_rules' => [
                            ['level' => 1, 'trigger_after_hours' => $slaConfig['response_time_hours'] / 2, 'action' => 'notify_supervisor'],
                            ['level' => 2, 'trigger_after_hours' => $slaConfig['response_time_hours'], 'action' => 'reassign'],
                            ['level' => 3, 'trigger_after_hours' => $slaConfig['resolution_time_hours'], 'action' => 'escalate_priority'],
                        ],
                    ]
                );
            }

            // 15. Tickets
            $ticket1 = Ticket::create([
                'title' => 'Проблема с доступом к системе',
                'description' => 'Не могу войти в личный кабинет, показывает ошибку авторизации',
                'category' => 'technical',
                'priority' => 'high',
                'status' => 'open',
                'sla_level' => 'silver',
                'created_by' => $studentModels['student1@college.edu']->id,
                'assigned_to' => $admin->id,
            ]);

            $ticket2 = Ticket::create([
                'title' => 'Запрос на справку об обучении',
                'description' => 'Необходима справка об обучении для предоставления в военкомат',
                'category' => 'administrative',
                'priority' => 'normal',
                'status' => 'in_progress',
                'sla_level' => 'bronze',
                'created_by' => $studentModels['student5@college.edu']->id,
                'assigned_to' => $methodist->id,
            ]);

            // 16. Basic Rules
            $rules = [
                [
                    'name' => 'Уведомление о новом задании',
                    'enabled' => true,
                    'scope' => 'global',
                    'conditions_json' => [
                        'operator' => 'AND',
                        'conditions' => [
                            ['field' => 'event_type', 'operator' => 'equals', 'value' => 'assignment.created']
                        ]
                    ],
                    'actions_json' => [
                        [
                            'type' => 'send_notification',
                            'config' => [
                                'title' => 'Новое задание',
                                'message' => 'Добавлено новое задание: {{assignment.title}}',
                                'recipients' => 'students'
                            ]
                        ]
                    ],
                    'created_by' => $admin->id,
                ],
                [
                    'name' => 'Эскалация просроченных заданий',
                    'enabled' => true,
                    'scope' => 'global',
                    'conditions_json' => [
                        'operator' => 'AND',
                        'conditions' => [
                            ['field' => 'event_type', 'operator' => 'equals', 'value' => 'assignment.overdue']
                        ]
                    ],
                    'actions_json' => [
                        [
                            'type' => 'send_notification',
                            'config' => [
                                'title' => 'Просроченное задание',
                                'message' => 'Задание "{{assignment.title}}" просрочено',
                                'recipients' => 'curator'
                            ]
                        ]
                    ],
                    'created_by' => $admin->id,
                ],
            ];

            foreach ($rules as $ruleData) {
                Rule::create($ruleData);
            }

            // 17. Risk Analytics
            $risks = [
                [
                    'entity_type' => 'student',
                    'entity_id' => $studentModels['student3@college.edu']->id,
                    'risk_level' => 'medium',
                    'risk_score' => 65.5,
                    'factors' => [
                        'low_grades' => true,
                        'missed_classes' => true,
                        'overdue_assignments' => false,
                    ],
                    'metadata' => [
                        'avg_grade' => 3.2,
                        'attendance_rate' => 0.75,
                        'overdue_count' => 0,
                    ],
                    'calculated_by' => $admin->id,
                    'calculated_at' => now(),
                ],
                [
                    'entity_type' => 'student',
                    'entity_id' => $studentModels['student7@college.edu']->id,
                    'risk_level' => 'high',
                    'risk_score' => 85.0,
                    'factors' => [
                        'low_grades' => true,
                        'missed_classes' => true,
                        'overdue_assignments' => true,
                    ],
                    'metadata' => [
                        'avg_grade' => 2.8,
                        'attendance_rate' => 0.60,
                        'overdue_count' => 2,
                    ],
                    'calculated_by' => $admin->id,
                    'calculated_at' => now(),
                ],
            ];

            foreach ($risks as $riskData) {
                Risk::create($riskData);
            }

            // 18. Contests
            $contest1 = Contest::create([
                'title' => 'Олимпиада по программированию',
                'description' => 'Ежегодная олимпиада по программированию для студентов IT-специальностей',
                'start_date' => now()->addDays(30),
                'end_date' => now()->addDays(37),
                'visibility_scope' => 'all',
                'status' => 'draft',
                'created_by' => $methodist->id,
            ]);

            ContestTarget::create([
                'contest_id' => $contest1->id,
                'group_id' => $groupModels['1IS-11']->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            ContestTarget::create([
                'contest_id' => $contest1->id,
                'group_id' => $groupModels['2IS-31']->id,
                'subgroup_id' => null,
                'student_user_id' => null,
            ]);

            // 19. Exams
            $exam1 = Exam::create([
                'type' => 'exam',
                'title' => 'Экзамен по математике',
                'subject_id' => $subjectModels['MATH']->id,
                'group_id' => $groupModels['1IS-11']->id,
                'exam_date' => now()->addDays(45),
                'room_id' => $roomModels['101']->id,
                'duration_minutes' => 90,
                'created_by' => $methodist->id,
            ]);

            // 20. Notifications
            $notifications = [
                [
                    'user_id' => $studentModels['student1@college.edu']->id,
                    'title' => 'Новое задание по математике',
                    'content' => 'Добавлено новое задание: Контрольная работа: Алгебраические уравнения',
                    'type' => 'assignment',
                    'priority' => 'normal',
                    'read_at' => null,
                ],
                [
                    'user_id' => $curatorModels['curator1@college.edu']->id,
                    'title' => 'Студент с высоким риском',
                    'content' => 'Студент Волков Дмитрий Александрович имеет высокий риск академической неуспешности',
                    'type' => 'risk',
                    'priority' => 'high',
                    'read_at' => null,
                ],
                [
                    'user_id' => $teacherModels['math.teacher@college.edu']->id,
                    'title' => 'Расписание изменено',
                    'content' => 'Урок математики в группе 1ИС-11 перенесен на другое время',
                    'type' => 'schedule',
                    'priority' => 'normal',
                    'read_at' => now()->subHour(),
                ],
            ];

            foreach ($notifications as $notificationData) {
                Notification::create($notificationData);
            }

            $this->command->info('✅ Full demo database seeded successfully!');
            $this->command->info('📊 Created:');
            $this->command->info('   - 8 Groups with subgroups');
            $this->command->info('   - 10 Subjects');
            $this->command->info('   - 8 Rooms');
            $this->command->info('   - 8 Time slots');
            $this->command->info('   - 19+ Users (admin, teachers, students, parents, curators)');
            $this->command->info('   - Full schedule for current week');
            $this->command->info('   - Lessons with grades');
            $this->command->info('   - Assignments and materials');
            $this->command->info('   - Chat with messages');
            $this->command->info('   - SLA configurations');
            $this->command->info('   - Tickets');
            $this->command->info('   - Rules');
            $this->command->info('   - Risk analytics');
            $this->command->info('   - Contest and exam');
            $this->command->info('   - Notifications');
        });
    }
}