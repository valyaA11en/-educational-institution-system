<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Приказ</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 30mm;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 14pt;
            line-height: 1.5;
            color: #000;
        }
        
        .organization {
            text-align: center;
            font-weight: bold;
            font-size: 16pt;
            margin-bottom: 10mm;
            text-transform: uppercase;
        }
        
        .document-type {
            text-align: center;
            font-weight: bold;
            font-size: 16pt;
            margin-bottom: 5mm;
            text-transform: uppercase;
        }
        
        .document-number {
            text-align: right;
            margin-bottom: 5mm;
            font-size: 14pt;
        }
        
        .document-title {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin: 10mm 0;
            text-transform: uppercase;
        }
        
        .document-basis {
            margin: 5mm 0;
            text-indent: 0;
        }
        
        .document-basis strong {
            font-weight: bold;
        }
        
        .document-content {
            margin: 10mm 0;
            text-align: justify;
        }
        
        .document-content p {
            margin-bottom: 5mm;
            text-indent: 12.5mm;
        }
        
        .document-content ol,
        .document-content ul {
            margin-left: 25mm;
            margin-bottom: 5mm;
        }
        
        .document-content li {
            margin-bottom: 3mm;
        }
        
        .signature-block {
            margin-top: 15mm;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-left {
            width: 60%;
        }
        
        .signature-right {
            width: 35%;
            text-align: right;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40mm;
            padding-top: 2mm;
            font-size: 12pt;
        }
        
        .signature-name {
            margin-top: 2mm;
            font-size: 12pt;
        }
        
        .acknowledgment {
            margin-top: 20mm;
            page-break-inside: avoid;
        }
        
        .acknowledgment-title {
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 5mm;
        }
        
        .acknowledgment-list {
            margin-left: 0;
            list-style: none;
        }
        
        .acknowledgment-item {
            margin-bottom: 3mm;
            display: flex;
            justify-content: space-between;
            padding-bottom: 2mm;
            border-bottom: 1px dotted #000;
        }
        
        .acknowledgment-name {
            width: 60%;
        }
        
        .acknowledgment-signature {
            width: 30%;
            text-align: center;
        }
        
        .acknowledgment-date {
            width: 10%;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="organization">
        {{ $tenant->name ?? 'УЧЕБНОЕ ЗАВЕДЕНИЕ' }}
    </div>
    
    <div class="document-type">ПРИКАЗ</div>
    
    <div class="document-number">
        № {{ $document->number ?? '' }} от {{ $document->date ? \Carbon\Carbon::parse($document->date)->format('d.m.Y') : '' }}
    </div>
    
    @if(!empty($data['title']))
        <div class="document-title">
            {{ $data['title'] }}
        </div>
    @endif
    
    @if(!empty($data['basis']))
        <div class="document-basis">
            <strong>Основание:</strong> {{ $data['basis'] }}
        </div>
    @endif
    
    <div class="document-content">
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
    
    <div class="signature-block">
        <div class="signature-left">
            <div class="signature-line">
                {{ $document->signer->fio ?? 'Руководитель' }}
            </div>
            <div class="signature-name">
                @if($document->signed_at)
                    {{ \Carbon\Carbon::parse($document->signed_at)->format('d.m.Y') }}
                @endif
            </div>
        </div>
        <div class="signature-right">
            <div class="signature-line"></div>
        </div>
    </div>
    
    @if($acks && $acks->count() > 0)
        <div class="acknowledgment">
            <div class="acknowledgment-title">С ОЗНАКОМЛЕНИЕМ:</div>
            <ul class="acknowledgment-list">
                @foreach($acks as $ack)
                    <li class="acknowledgment-item">
                        <span class="acknowledgment-name">{{ $ack->user->fio ?? '' }}</span>
                        <span class="acknowledgment-signature">
                            @if($ack->status === 'confirmed' && $ack->confirmed_at)
                                _________________
                            @else
                                (не ознакомлен)
                            @endif
                        </span>
                        <span class="acknowledgment-date">
                            @if($ack->status === 'confirmed' && $ack->confirmed_at)
                                {{ \Carbon\Carbon::parse($ack->confirmed_at)->format('d.m.Y') }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>

