@extends('print.layout')

@section('content')
<div class="student-portfolio-export">
    <h1>Портфолио студента</h1>
    
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
                <td><strong>Дата экспорта:</strong></td>
                <td>{{ $export_date->format('d.m.Y H:i') }}</td>
            </tr>
        </table>
    </div>

    @if(count($portfolio) > 0)
        @foreach($portfolio as $type => $items)
            <div class="type-section" style="page-break-inside: avoid;">
                <h2>
                    @php
                        $typeLabels = [
                            'assignment' => 'Задания',
                            'contest' => 'Конкурсы',
                            'certificate' => 'Сертификаты',
                            'achievement' => 'Достижения',
                        ];
                        echo $typeLabels[$type] ?? ucfirst($type);
                    @endphp
                    ({{ count($items) }})
                </h2>
                
                <div class="items-grid">
                    @foreach($items as $item)
                        <div class="portfolio-item">
                            <div class="item-header">
                                <h3>{{ $item['title'] }}</h3>
                                @if($item['is_featured'])
                                    <span class="featured-badge">★ Избранное</span>
                                @endif
                            </div>
                            @if($item['description'])
                                <p class="item-description">{{ $item['description'] }}</p>
                            @endif
                            <div class="item-meta">
                                <span class="item-date">
                                    {{ \Carbon\Carbon::parse($item['created_at'])->format('d.m.Y') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="no-items">
            <p>Портфолио пусто</p>
        </div>
    @endif

    <div class="footer">
        <p>Учреждение: {{ $tenant->name ?? 'N/A' }}</p>
    </div>
</div>

<style>
.student-portfolio-export {
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

.type-section {
    margin-bottom: 40px;
}

.type-section h2 {
    color: #1976d2;
    border-bottom: 2px solid #1976d2;
    padding-bottom: 5px;
    margin-bottom: 20px;
}

.items-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.portfolio-item {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background-color: #f9f9f9;
}

.portfolio-item.featured {
    border-color: #ffc107;
    background-color: #fffbf0;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.item-header h3 {
    margin: 0;
    font-size: 14pt;
    color: #333;
}

.featured-badge {
    background-color: #ffc107;
    color: #000;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 9pt;
    font-weight: bold;
}

.item-description {
    font-size: 10pt;
    color: #666;
    margin-bottom: 10px;
}

.item-meta {
    font-size: 9pt;
    color: #999;
}

.footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    font-size: 10pt;
    color: #666;
}

.no-items {
    text-align: center;
    padding: 40px;
    color: #999;
}
</style>
@endsection


