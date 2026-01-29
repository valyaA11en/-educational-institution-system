@extends('print.layout')

@section('content')
    <div class="no-break">
        <p><strong>Группа:</strong> {{ $group->name }}</p>
        @if($subject)
            <p><strong>Предмет:</strong> {{ $subject->name }}</p>
        @endif
        @if($term)
            <p><strong>Семестр:</strong> {{ $term->name }}</p>
        @endif
    </div>
    
    <table class="no-break">
        <thead>
            <tr>
                <th style="width: 5%;">№</th>
                <th style="width: 30%;">ФИО студента</th>
                @foreach($lessons as $lesson)
                    <th style="width: {{ 65 / max(count($lessons), 1) }}%; font-size: 7pt;">
                        {{ \Carbon\Carbon::parse($lesson->date)->format('d.m') }}<br>
                        {{ $lesson->scheduleItem->subject->name ?? '' }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $student->fio }}</td>
                    @foreach($lessons as $lesson)
                        <td style="text-align: center;">
                            @php
                                $grade = $grades->get($student->id)?->get($lesson->id)?->first();
                            @endphp
                            @if($grade && $grade->first())
                                @php $g = $grade->first(); @endphp
                                {{ $g->value ?? '-' }}
                                @if($g->assignment)
                                    <small>({{ $g->assignment->title }})</small>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="signatures" style="margin-top: 20mm;">
        <div class="signature-block">
            <div class="signature-line">Преподаватель</div>
        </div>
        <div class="signature-block">
            <div class="signature-line">Куратор группы</div>
        </div>
    </div>
@endsection

