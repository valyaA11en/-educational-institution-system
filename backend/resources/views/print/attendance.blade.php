@extends('print.layout')

@section('content')
    <div class="no-break">
        <p><strong>Группа:</strong> {{ $group->name }}</p>
        <p><strong>Период:</strong> {{ $from->format('d.m.Y') }} - {{ $to->format('d.m.Y') }}</p>
    </div>
    
    @php
        $dates = [];
        $current = $from->copy();
        while ($current->lte($to)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }
    @endphp
    
    <table class="no-break">
        <thead>
            <tr>
                <th style="width: 5%;">№</th>
                <th style="width: 25%;">ФИО студента</th>
                @foreach($dates as $date)
                    <th style="width: {{ 70 / count($dates) }}%; font-size: 7pt; text-align: center;">
                        {{ \Carbon\Carbon::parse($date)->format('d.m') }}
                    </th>
                @endforeach
                <th style="width: 10%; text-align: center;">Всего</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
                @php
                    $studentAttendance = $attendance->get($student->id) ?? collect();
                    $totalPresent = 0;
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $student->fio }}</td>
                    @foreach($dates as $date)
                        @php
                            $dayAttendance = $studentAttendance->get($date);
                            $isPresent = $dayAttendance && $dayAttendance->first() && $dayAttendance->first()->status === 'present';
                            if ($isPresent) $totalPresent++;
                        @endphp
                        <td style="text-align: center;">
                            @if($dayAttendance && $dayAttendance->first())
                                @if($dayAttendance->first()->status === 'present')
                                    +
                                @elseif($dayAttendance->first()->status === 'absent')
                                    -
                                @elseif($dayAttendance->first()->status === 'late')
                                    оп
                                @else
                                    {{ $dayAttendance->first()->status }}
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    @endforeach
                    <td style="text-align: center; font-weight: bold;">{{ $totalPresent }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div style="margin-top: 5mm; font-size: 8pt;">
        <p><strong>Условные обозначения:</strong></p>
        <p>+ - присутствовал, - - отсутствовал, оп - опоздал</p>
    </div>
    
    <div class="signatures" style="margin-top: 20mm;">
        <div class="signature-block">
            <div class="signature-line">Преподаватель</div>
        </div>
        <div class="signature-block">
            <div class="signature-line">Куратор группы</div>
        </div>
    </div>
@endsection

