@extends('print.layout')

@section('content')
    <div class="no-break">
        <p><strong>Период:</strong> {{ $from->format('d.m.Y') }} - {{ $to->format('d.m.Y') }}</p>
    </div>
    
    @foreach($items as $date => $dayItems)
        <div class="page-break">
            <h2 style="margin-bottom: 5mm; font-size: 12pt;">{{ \Carbon\Carbon::parse($date)->locale('ru')->isoFormat('dddd, D MMMM YYYY') }}</h2>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">Время</th>
                        <th style="width: 20%;">Группа</th>
                        <th style="width: 25%;">Предмет</th>
                        <th style="width: 20%;">Преподаватель</th>
                        <th style="width: 15%;">Кабинет</th>
                        <th style="width: 10%;">Подгруппа</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dayItems as $item)
                        <tr>
                            <td style="text-align: center;">
                                @php
                                    $timeSlot = $item->time_slot_id ? \App\Models\TimeSlot::find($item->time_slot_id) : null;
                                @endphp
                                @if($timeSlot)
                                    {{ $timeSlot->start_time ?? '' }} - {{ $timeSlot->end_time ?? '' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $item->group->name ?? '-' }}</td>
                            <td>{{ $item->subject->name ?? '-' }}</td>
                            <td>{{ $item->teacher->fio ?? '-' }}</td>
                            <td>{{ $item->room->name ?? '-' }}</td>
                            <td style="text-align: center;">{{ $item->subgroup->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
    
    <div class="signatures">
        <div class="signature-block">
            <div class="signature-line">Подпись ответственного лица</div>
        </div>
        <div class="signature-block">
            <div class="signature-line">Дата</div>
        </div>
    </div>
@endsection

