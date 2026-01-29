<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ведомость</title>
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
        <h2>Ведомость</h2>
        <p>{{ $exam->title ?? '' }}</p>
        <p>Дата: {{ $exam->date_at ?? '' }}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>ФИО</th>
                <th>Оценка</th>
                <th>Балл</th>
                <th>Комментарий</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results ?? [] as $index => $result)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $result->student->fio ?? '' }}</td>
                    <td>{{ $result->grade_value ?? '' }}</td>
                    <td>{{ $result->score ?? '' }}</td>
                    <td>{{ $result->comment ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div style="margin-top: 40px;">
        <p>Комиссия:</p>
        @foreach($commission ?? [] as $member)
            <p>{{ $member->user->fio ?? '' }} - {{ $member->role }}</p>
        @endforeach
    </div>
</body>
</html>


