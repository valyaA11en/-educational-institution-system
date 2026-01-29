@extends('print.layout')

@section('content')
<div class="student-export">
    <h1>Экспорт данных студента</h1>
    
    <div class="student-info">
        <h2>Основная информация</h2>
        <table>
            <tr>
                <td><strong>ФИО:</strong></td>
                <td>{{ $student->fio }}</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td>{{ $student->email }}</td>
            </tr>
            <tr>
                <td><strong>Группы:</strong></td>
                <td>{{ $groups->pluck('name')->join(', ') }}</td>
            </tr>
        </table>
    </div>

    <div class="statistics">
        <h2>Статистика</h2>
        <table>
            <tr>
                <td><strong>Всего оценок:</strong></td>
                <td>{{ $statistics['total_grades'] }}</td>
            </tr>
            <tr>
                <td><strong>Средний балл:</strong></td>
                <td>{{ $statistics['average_grade'] }}</td>
            </tr>
            <tr>
                <td><strong>Посещаемость:</strong></td>
                <td>{{ $statistics['attendance_rate'] }}%</td>
            </tr>
            <tr>
                <td><strong>Выполнено заданий:</strong></td>
                <td>{{ $statistics['completed_assignments'] }} / {{ $statistics['total_assignments'] }}</td>
            </tr>
        </table>
    </div>

    @if(count($grades) > 0)
    <div class="grades">
        <h2>Оценки (последний год)</h2>
        <table>
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Предмет</th>
                    <th>Тема</th>
                    <th>Оценка</th>
                    <th>Тип</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grades->take(50) as $grade)
                <tr>
                    <td>{{ $grade->created_at->format('d.m.Y') }}</td>
                    <td>{{ $grade->lesson?->scheduleItem?->subject?->name ?? $grade->assignment?->subject?->name ?? '-' }}</td>
                    <td>{{ $grade->lesson?->topic ?? $grade->assignment?->title ?? '-' }}</td>
                    <td>{{ $grade->value }}</td>
                    <td>{{ $grade->grade_type }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(count($failed_topics) > 0)
    <div class="failed-topics">
        <h2>Проблемные темы</h2>
        <table>
            <thead>
                <tr>
                    <th>Предмет</th>
                    <th>Тема</th>
                    <th>Неудачных попыток</th>
                    <th>Средний балл</th>
                </tr>
            </thead>
            <tbody>
                @foreach($failed_topics as $topic)
                <tr>
                    <td>{{ $topic['subject_name'] }}</td>
                    <td>{{ $topic['topic_name'] }}</td>
                    <td>{{ $topic['failed_attempts'] }}</td>
                    <td>{{ $topic['average_grade'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(count($portfolio) > 0)
    <div class="portfolio">
        <h2>Портфолио</h2>
        <table>
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Тип</th>
                    <th>Название</th>
                    <th>Описание</th>
                </tr>
            </thead>
            <tbody>
                @foreach($portfolio as $item)
                <tr>
                    <td>{{ $item->date->format('d.m.Y') }}</td>
                    <td>{{ $item->type }}</td>
                    <td>{{ $item->title }}</td>
                    <td>{{ $item->description }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>Дата экспорта: {{ $export_date->format('d.m.Y H:i') }}</p>
        <p>Учреждение: {{ $tenant->name ?? 'N/A' }}</p>
    </div>
</div>
@endsection


