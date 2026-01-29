<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Приказ</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; }
        .header { text-align: center; margin-bottom: 30px; }
        .content { margin: 20px 0; line-height: 1.6; }
        .signature { margin-top: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>ПРИКАЗ</h2>
        <p>№ {{ $orderNumber ?? '' }} от {{ $orderDate ?? '' }}</p>
    </div>
    
    <div class="content">
        <p><strong>{{ $title ?? '' }}</strong></p>
        <p>{{ $content ?? '' }}</p>
    </div>
    
    <div class="signature">
        <p>Руководитель: _________________ {{ $director ?? '' }}</p>
    </div>
</body>
</html>


