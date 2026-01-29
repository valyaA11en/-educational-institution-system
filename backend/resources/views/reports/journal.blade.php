<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Журнал</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        th { background-color: #f0f0f0; }
        .header { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Журнал успеваемости</h2>
        <p>Группа: {{ $group->name ?? '' }}</p>
        <p>Предмет: {{ $subject->name ?? '' }}</p>
        <p>Период: {{ $period ?? '' }}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>ФИО</th>
                @foreach($dates ?? [] as $date)
                    <th>{{ $date }}</th>
                @endforeach
                <th>Итого</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students ?? [] as $index => $student)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $student->fio }}</td>
                    @foreach($dates ?? [] as $date)
                        <td>{{ $grades[$student->id][$date] ?? '' }}</td>
                    @endforeach
                    <td>{{ $totals[$student->id] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>


