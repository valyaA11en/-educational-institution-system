@extends('print.layout')

@section('content')
<div class="student-timeline-export">
    <h1>История обучения</h1>
    
    <div class="student-info">
        <table>
            <tr>
                <td><strong>Студент:</strong></td>
                <td>{{ $student->fio }}</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td>{{ $student->email }}</td>
            </tr>
            <tr>
                <td><strong>Группы:</strong></td>
                <td>{{ $groups->pluck('name')->join(', ') ?: 'Не указано' }}</td>
            </tr>
            <tr>
                <td><strong>Период:</strong></td>
                <td>
                    @if(isset($filters['dateFrom']) || isset($filters['dateTo']))
                        {{ $filters['dateFrom'] ?? 'Начало' }} - {{ $filters['dateTo'] ?? 'Конец' }}
                    @else
                        Весь период
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if(count($timeline) > 0)
        @foreach($timeline as $monthKey => $monthData)
            <div class="month-section" style="page-break-inside: avoid;">
                <h2>{{ $monthData['label'] }}</h2>
                
                <table class="timeline-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Дата</th>
                            <th style="width: 20%;">Тип</th>
                            <th style="width: 35%;">Событие</th>
                            <th style="width: 30%;">Описание</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthData['events'] as $event)
                        <tr>
                            <td>{{ \Carbon\Carbon::createFromFormat('Y-m-d', $event['event_date'])->format('d.m.Y') }}</td>
                            <td>
                                @php
                                    $typeLabels = [
                                        'enrollment.created' => 'Зачисление',
                                        'attendance.marked' => 'Посещаемость',
                                        'grade.created' => 'Оценка',
                                        'grade.updated' => 'Изменение оценки',
                                        'assignment.submitted' => 'Задание сдано',
                                        'assignment.late' => 'Задание с опозданием',
                                        'risk.updated' => 'Риск',
                                        'document.created' => 'Документ',
                                        'contest.result' => 'Результат конкурса',
                                        'exam.result' => 'Результат экзамена',
                                    ];
                                    echo $typeLabels[$event['event_type']] ?? $event['event_type'];
                                @endphp
                            </td>
                            <td><strong>{{ $event['title'] }}</strong></td>
                            <td>{{ $event['description'] ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <div class="no-events">
            <p>Нет событий за выбранный период</p>
        </div>
    @endif

    <div class="footer">
        <p>Дата экспорта: {{ $export_date->format('d.m.Y H:i') }}</p>
        <p>Учреждение: {{ $tenant->name ?? 'N/A' }}</p>
    </div>
</div>

<style>
.student-timeline-export {
    font-family: Arial, sans-serif;
}

.student-info {
    margin-bottom: 30px;
}

.student-info table {
    width: 100%;
    border-collapse: collapse;
}

.student-info table td {
    padding: 5px 10px;
    border-bottom: 1px solid #eee;
}

.month-section {
    margin-bottom: 40px;
}

.month-section h2 {
    color: #1976d2;
    border-bottom: 2px solid #1976d2;
    padding-bottom: 5px;
    margin-bottom: 15px;
}

.timeline-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.timeline-table thead {
    background-color: #f5f5f5;
}

.timeline-table th,
.timeline-table td {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
}

.timeline-table tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    font-size: 10pt;
    color: #666;
}
</style>
@endsection

