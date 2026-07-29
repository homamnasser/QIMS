<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>شهادة نتيجة نهائية - {{ $certificate['serial_number'] }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            width: 297mm;
            height: 210mm;
            color: #173f3a;
            background: #f5f0e5;
            font-family: "Noto Naskh Arabic", "Noto Sans Arabic", serif;
        }
        .sheet {
            position: relative;
            width: 100%;
            height: 100%;
            padding: 14mm;
            overflow: hidden;
        }
        .sheet::before, .sheet::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: #c99a3d;
            opacity: .11;
        }
        .sheet::before { width: 110mm; height: 110mm; top: -66mm; right: -38mm; }
        .sheet::after { width: 85mm; height: 85mm; bottom: -52mm; left: -28mm; }
        .frame {
            position: relative;
            z-index: 2;
            height: 100%;
            border: 1.5mm solid #184942;
            outline: .45mm solid #c99a3d;
            outline-offset: -4mm;
            padding: 10mm 13mm 8mm;
            background: rgba(255, 253, 247, .94);
        }
        .header { text-align: center; }
        .eyebrow {
            color: #9b6c1d;
            font-size: 12pt;
            letter-spacing: .8px;
        }
        h1 {
            margin: 1mm 0 0;
            color: #184942;
            font-size: 30pt;
            line-height: 1.25;
        }
        .issuer { margin-top: 1mm; font-size: 14pt; }
        .intro { margin: 4mm 0 1mm; text-align: center; font-size: 13pt; }
        .student {
            width: 72%;
            margin: 0 auto 3mm;
            border-bottom: .5mm solid #c99a3d;
            color: #9b6c1d;
            text-align: center;
            font-size: 25pt;
            font-weight: 700;
            line-height: 1.5;
        }
        .statement {
            width: 82%;
            margin: 0 auto 4mm;
            text-align: center;
            font-size: 13pt;
            line-height: 1.65;
        }
        .score-band {
            display: flex;
            justify-content: center;
            gap: 4mm;
            margin-bottom: 4mm;
        }
        .score-card {
            min-width: 43mm;
            padding: 2.5mm 4mm;
            border: .35mm solid #d8c7a2;
            background: #fbf7ed;
            text-align: center;
            border-radius: 2mm;
        }
        .score-card b { display: block; color: #184942; font-size: 19pt; }
        .score-card span { color: #6f6a5f; font-size: 10pt; }
        .criteria {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2mm;
            margin: 0 auto;
            width: 85%;
        }
        .criterion {
            min-width: 29mm;
            padding: 1.5mm 2.5mm;
            border-inline-start: 1mm solid #c99a3d;
            background: #f3efe5;
            font-size: 9.5pt;
        }
        .criterion strong { color: #184942; }
        .footer {
            position: absolute;
            right: 13mm;
            left: 13mm;
            bottom: 8mm;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        .meta { font-size: 9pt; line-height: 1.7; color: #5f625d; }
        .verification { display: flex; align-items: center; gap: 3mm; direction: ltr; }
        .verification svg { width: 25mm; height: 25mm; }
        .verify-text {
            max-width: 70mm;
            direction: rtl;
            font-size: 8.5pt;
            color: #5f625d;
            word-break: break-all;
        }
        .seal {
            position: absolute;
            bottom: 9mm;
            left: 50%;
            transform: translateX(-50%);
            width: 25mm;
            height: 25mm;
            border: 1mm double #c99a3d;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #9b6c1d;
            font-size: 9pt;
            text-align: center;
            background: #fffaf0;
        }
    </style>
</head>
<body>
<main class="sheet">
    <section class="frame">
        <header class="header">
            <div class="eyebrow">{{ $certificate['project']['name'] }}</div>
            <h1>شهادة نتيجة نهائية</h1>
            <div class="issuer">{{ $certificate['issuer_name'] }}</div>
        </header>

        <p class="intro">تشهد بأن الطالب</p>
        <div class="student">{{ $certificate['student']['name'] }}</div>
        <p class="statement">
            أتم دورة التقييم «{{ $certificate['cycle']['name'] }}» خلال الفترة
            <bdi dir="ltr">{{ $certificate['cycle']['start_date'] }} – {{ $certificate['cycle']['end_date'] }}</bdi>
            واستحق هذه النتيجة بعد اعتماد جميع معايير التقييم.
        </p>

        <div class="score-band">
            <div class="score-card">
                <b>{{ number_format($certificate['result']['final_score'], 2) }}</b>
                <span>المجموع النهائي</span>
            </div>
            <div class="score-card">
                <b>{{ number_format($certificate['result']['final_percentage'], 2) }}%</b>
                <span>النسبة النهائية مع المكافآت</span>
            </div>
            <div class="score-card">
                <b>{{ $certificate['result']['is_excellent'] ? 'متميز' : 'مشارك' }}</b>
                <span>حالة التميز</span>
            </div>
            @if($certificate['result']['rank'])
                <div class="score-card">
                    <b>{{ $certificate['result']['rank'] }}</b>
                    <span>الترتيب المعتمد</span>
                </div>
            @endif
        </div>

        <div class="criteria">
            @foreach($certificate['criteria'] as $criterion)
                @if($criterion['is_applicable'])
                    <div class="criterion">
                        {{ $criterion['name'] }}:
                        <strong><bdi dir="ltr">{{ number_format($criterion['score'], 2) }} / {{ number_format($criterion['maximum_score'], 2) }}</bdi></strong>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="seal">نتيجة<br>معتمدة</div>
        <footer class="footer">
            <div class="meta">
                <div>الرقم الذاتي: <bdi dir="ltr">{{ $certificate['student']['selfnumber'] ?: '—' }}</bdi></div>
                <div>رقم الشهادة: <bdi dir="ltr">{{ $certificate['serial_number'] }}</bdi></div>
                <div>تاريخ الإصدار: <bdi dir="ltr">{{ substr($certificate['issued_at'], 0, 10) }}</bdi></div>
            </div>
            <div class="verification">
                <div class="verify-text">
                    امسح الرمز للتحقق من سريان الشهادة، أو استخدم رقمها في النظام.
                </div>
                {!! $qrSvg !!}
            </div>
        </footer>
    </section>
</main>
</body>
</html>
