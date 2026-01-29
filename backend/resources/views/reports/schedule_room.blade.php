<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Расписание - {{ $room->name ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: center; }
        th { background-color: #f0f0f0; }
        .header { text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Расписание занятий</h2>
        <p>Кабинет: {{ $room->name ?? '' }}</p>
        <p>Период: {{ $period ?? '' }}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>День</th>
                <th>Время</th>
                <th>Группа</th>
                <th>Предмет</th>
                <th>Преподаватель</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedule ?? [] as $item)
                <tr>
                    <td>{{ $item->date ?? '' }}</td>
                    <td>{{ $item->time_slot->name ?? '' }}</td>
                    <td>{{ $item->group->name ?? '' }}</td>
                    <td>{{ $item->subject->name ?? '' }}</td>
                    <td>{{ $item->teacher->fio ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>


