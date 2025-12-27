<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Документ</title>
    <style>
        @page {
            margin: 20mm;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15mm;
            border-bottom: 2px solid #000;
            padding-bottom: 5mm;
        }
        
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 3mm;
            text-transform: uppercase;
        }
        
        .header .tenant {
            font-size: 14pt;
            margin-bottom: 2mm;
        }
        
        .header .document-info {
            font-size: 11pt;
            color: #666;
        }
        
        .content {
            margin: 10mm 0;
            text-align: justify;
        }
        
        .content p {
            margin-bottom: 5mm;
            text-indent: 12.5mm;
        }
        
        .signature {
            margin-top: 20mm;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-block {
            width: 45%;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 30mm;
            padding-top: 2mm;
            font-size: 11pt;
        }
        
        .acknowledgment {
            margin-top: 15mm;
            page-break-inside: avoid;
        }
        
        .acknowledgment-title {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 5mm;
        }
        
        .acknowledgment-list {
            margin-left: 0;
            list-style: none;
        }
        
        .acknowledgment-item {
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px dotted #000;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="tenant">{{ $tenant->name ?? 'Учебное заведение' }}</div>
        <h1>{{ strtoupper($document->type ?? 'ДОКУМЕНТ') }}</h1>
        <div class="document-info">
            @if($document->number)
                № {{ $document->number }}
            @endif
            @if($document->date)
                от {{ \Carbon\Carbon::parse($document->date)->format('d.m.Y') }}
            @endif
        </div>
    </div>
    
    <div class="content">
        @if(!empty($data['title']))
            <p><strong>{{ $data['title'] }}</strong></p>
        @endif
        
        @if(!empty($data['basis']))
            <p><strong>Основание:</strong> {{ $data['basis'] }}</p>
        @endif
        
        @if(!empty($data['content']))
            @if(is_array($data['content']))
                @if(isset($data['content']['items']))
                    <ol>
                        @foreach($data['content']['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ol>
                @else
                    @foreach($data['content'] as $item)
                        <p>{{ $item }}</p>
                    @endforeach
                @endif
            @else
                <p>{{ $data['content'] }}</p>
            @endif
        @endif
    </div>
    
    <div class="signature">
        <div class="signature-block">
            <div class="signature-line">
                {{ $document->signer->fio ?? 'Подпись' }}
            </div>
            @if($document->signed_at)
                <div style="margin-top: 2mm; font-size: 10pt;">
                    {{ \Carbon\Carbon::parse($document->signed_at)->format('d.m.Y') }}
                </div>
            @endif
        </div>
    </div>
    
    @if($acks && $acks->count() > 0)
        <div class="acknowledgment">
            <div class="acknowledgment-title">Ознакомление:</div>
            <ul class="acknowledgment-list">
                @foreach($acks as $ack)
                    <li class="acknowledgment-item">
                        {{ $ack->user->fio ?? '' }}
                        @if($ack->status === 'confirmed' && $ack->confirmed_at)
                            - ознакомлен {{ \Carbon\Carbon::parse($ack->confirmed_at)->format('d.m.Y') }}
                        @else
                            - не ознакомлен
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>

