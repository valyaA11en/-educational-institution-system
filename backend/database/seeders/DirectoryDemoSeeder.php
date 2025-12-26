<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Room;
use App\Models\Subject;
use App\Models\Subgroup;
use App\Models\TimeSlot;
use Illuminate\Database\Seeder;

class DirectoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Groups and subgroups
        $groups = [
            ['name' => '11A', 'code' => '11A'],
            ['name' => '11B', 'code' => '11B'],
        ];

        foreach ($groups as $groupData) {
            $group = Group::firstOrCreate(
                ['code' => $groupData['code']],
                ['name' => $groupData['name']],
            );

            foreach (['1', '2'] as $suffix) {
                Subgroup::firstOrCreate(
                    ['code' => "{$groupData['code']}-{$suffix}"],
                    [
                        'group_id' => $group->id,
                        'name' => "{$groupData['name']} подгруппа {$suffix}",
                    ],
                );
            }
        }

        // Subjects
        $subjects = [
            ['name' => 'Математика', 'code' => 'MATH'],
            ['name' => 'Физика', 'code' => 'PHYS'],
            ['name' => 'Информатика', 'code' => 'CS'],
        ];

        foreach ($subjects as $subject) {
            Subject::firstOrCreate(['code' => $subject['code']], ['name' => $subject['name']]);
        }

        // Rooms
        $rooms = [
            ['name' => 'Кабинет 101', 'code' => '101', 'capacity' => 30],
            ['name' => 'Кабинет 102', 'code' => '102', 'capacity' => 25],
            ['name' => 'Лаборатория 201', 'code' => '201', 'capacity' => 20],
        ];

        foreach ($rooms as $room) {
            Room::firstOrCreate(
                ['code' => $room['code']],
                [
                    'name' => $room['name'],
                    'capacity' => $room['capacity'],
                ],
            );
        }

        // Time slots
        $slots = [
            ['name' => '1 пара', 'start_time' => '08:30', 'end_time' => '09:15', 'order' => 1],
            ['name' => '2 пара', 'start_time' => '09:25', 'end_time' => '10:10', 'order' => 2],
            ['name' => '3 пара', 'start_time' => '10:30', 'end_time' => '11:15', 'order' => 3],
            ['name' => '4 пара', 'start_time' => '11:25', 'end_time' => '12:10', 'order' => 4],
        ];

        foreach ($slots as $slot) {
            TimeSlot::firstOrCreate(
                ['order' => $slot['order']],
                [
                    'name' => $slot['name'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ],
            );
        }
    }
}



