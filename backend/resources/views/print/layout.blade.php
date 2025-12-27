<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Печатная форма' }}</title>
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
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15mm;
            border-bottom: 2px solid #000;
            padding-bottom: 5mm;
        }
        
        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 3mm;
        }
        
        .header .tenant {
            font-size: 11pt;
            margin-bottom: 2mm;
        }
        
        .header .date {
            font-size: 9pt;
            color: #666;
        }
        
        .content {
            margin-bottom: 15mm;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            font-size: 9pt;
        }
        
        table th,
        table td {
            border: 1px solid #000;
            padding: 3mm;
            text-align: left;
            word-wrap: break-word;
        }
        
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #666;
            padding: 3mm;
            border-top: 1px solid #ccc;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .no-break {
            page-break-inside: avoid;
        }
        
        .signatures {
            margin-top: 15mm;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-block {
            width: 45%;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 20mm;
            padding-top: 2mm;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="tenant">{{ $tenant->name ?? 'Учебное заведение' }}</div>
        <h1>{{ $title ?? 'Печатная форма' }}</h1>
        <div class="date">Дата печати: {{ $printed_at->format('d.m.Y H:i') }}</div>
    </div>
    
    <div class="content">
        @yield('content')
    </div>
    
    <div class="footer">
        Страница <span class="page-number"></span>
    </div>
    
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Страница {PAGE_NUM} из {PAGE_COUNT}";
            $size = 8;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 20;
            $pdf->page_text($x, $y, $text, $font, $size);
        }
    </script>
</body>
</html>

