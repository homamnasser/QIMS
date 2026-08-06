@php
    // مسارات أصول الهوية البصرية؛ يقرؤها Chrome عبر file:// أثناء التوليد.
    $asset = fn (string $file) => $assetRoot.'/'.$file;
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>شهادة نتيجة نهائية - {{ $certificate['serial_number'] }}</title>
    <style>
        @font-face { font-family: "Tajawal"; font-weight: 400; src: url("{{ $asset('fonts/Tajawal-Regular.ttf') }}") format("truetype"); }
        @font-face { font-family: "Tajawal"; font-weight: 500; src: url("{{ $asset('fonts/Tajawal-Medium.ttf') }}") format("truetype"); }
        @font-face { font-family: "Tajawal"; font-weight: 700; src: url("{{ $asset('fonts/Tajawal-Bold.ttf') }}") format("truetype"); }
        @font-face { font-family: "Tajawal"; font-weight: 800; src: url("{{ $asset('fonts/Tajawal-ExtraBold.ttf') }}") format("truetype"); }

        :root {
            --navy: #2a315d;
            --teal: #458ca2;
            --teal-soft: #81c2c4;
            --sand: #debd87;
            --sand-ink: #9b6c1d;
            --ivory: #fbf9f5;
            --ink: #1d1d1d;
            --ink-muted: #5d6472;
        }

        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }

        body {
            margin: 0;
            width: 297mm;
            height: 210mm;
            color: var(--ink);
            background: var(--ivory);
            font-family: "Tajawal", "Noto Sans Arabic", sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet { position: relative; width: 100%; height: 100%; overflow: hidden; }

        /* خلفية الهوية: نسيج الخط العربي مخفّف حتى لا ينافس النص. */
        .texture {
            position: absolute;
            inset: 0;
            background: url("{{ $asset('texture.jpg') }}") center / cover no-repeat;
            opacity: .3;
        }
        .wash {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(80mm 60mm at 88% 6%, rgba(69, 140, 162, .13), transparent 70%),
                radial-gradient(70mm 55mm at 10% 96%, rgba(222, 189, 135, .2), transparent 70%),
                linear-gradient(160deg, rgba(251, 249, 245, .62), rgba(251, 249, 245, .9));
        }

        /* المآذن: العنصر نفسه المستخدم في الشعار، بلونٍ باهت أسفل الصفحة. */
        .minaret {
            position: absolute;
            bottom: -6mm;
            width: 48mm;
            height: 118mm;
            background: var(--teal);
            opacity: .07;
            -webkit-mask: url("{{ $asset('minaret.png') }}") no-repeat center bottom / contain;
        }
        .minaret.start { right: 8mm; }
        .minaret.end { left: 8mm; transform: scaleX(-1); }

        .frame {
            position: absolute;
            inset: 7mm;
            border: 1.1mm solid var(--navy);
            border-radius: 2mm;
            padding: 7mm 12mm 6mm;
            display: flex;
            flex-direction: column;
        }
        .frame::before {
            content: "";
            position: absolute;
            inset: 2.2mm;
            border: .35mm solid var(--sand);
            border-radius: 1mm;
        }
        /* أربطة ركنية بلون العلامة تكسر رتابة الإطار. */
        .corner {
            position: absolute;
            width: 16mm;
            height: 16mm;
            border: 1.1mm solid var(--teal);
        }
        .corner.tr { top: -1.1mm; right: -1.1mm; border-left: 0; border-bottom: 0; border-radius: 0 2mm 0 0; }
        .corner.tl { top: -1.1mm; left: -1.1mm; border-right: 0; border-bottom: 0; border-radius: 2mm 0 0 0; }
        .corner.br { bottom: -1.1mm; right: -1.1mm; border-left: 0; border-top: 0; border-radius: 0 0 2mm 0; }
        .corner.bl { bottom: -1.1mm; left: -1.1mm; border-right: 0; border-top: 0; border-radius: 0 0 0 2mm; }

        .header { position: relative; display: flex; align-items: center; justify-content: center; gap: 5mm; }
        .header img { width: 20mm; height: 20mm; object-fit: contain; }
        .identity { text-align: start; }
        .issuer { color: var(--navy); font-size: 14pt; font-weight: 800; line-height: 1.25; }
        .program { margin-top: .8mm; color: var(--teal); font-size: 10.5pt; font-weight: 500; }

        /* شارة التميّز تُثبَّت في الزاوية حتى لا تزيح توسيط الترويسة. */
        .excellence {
            position: absolute;
            top: 0;
            left: 0;
            display: flex;
            align-items: center;
            gap: 1.5mm;
            padding: 1.6mm 4mm;
            border: .35mm solid var(--sand);
            border-radius: 12mm;
            background: rgba(222, 189, 135, .18);
            color: var(--sand-ink);
            font-size: 9.5pt;
            font-weight: 700;
        }
        .excellence::before { content: "★"; font-size: 10pt; }

        .rule { display: flex; align-items: center; justify-content: center; gap: 2.5mm; margin: 4mm 0 0; }
        .rule i { display: block; width: 46mm; height: .35mm; background: linear-gradient(90deg, transparent, var(--sand)); }
        .rule i:last-child { background: linear-gradient(270deg, transparent, var(--sand)); }
        .rule b { width: 2.6mm; height: 2.6mm; background: var(--sand); transform: rotate(45deg); }

        .title {
            margin: 3.5mm 0 0;
            color: var(--navy);
            text-align: center;
            font-size: 30pt;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -.3px;
        }

        .intro { margin: 4mm 0 1.5mm; text-align: center; font-size: 12pt; color: var(--ink-muted); }

        .student {
            width: 66%;
            margin: 0 auto;
            padding-bottom: 2mm;
            border-bottom: .6mm solid var(--sand);
            color: var(--teal);
            text-align: center;
            font-size: 26pt;
            font-weight: 800;
            line-height: 1.35;
        }
        .student small { display: block; margin-top: 1mm; color: var(--ink-muted); font-size: 10pt; font-weight: 500; }

        .statement {
            width: 78%;
            margin: 3.5mm auto 0;
            text-align: center;
            font-size: 11.5pt;
            font-weight: 500;
            line-height: 1.7;
            color: var(--ink);
        }

        .scores { display: flex; justify-content: center; align-items: stretch; gap: 3mm; margin-top: 5mm; }
        .score {
            min-width: 40mm;
            padding: 3mm 5mm;
            border: .35mm solid rgba(42, 49, 93, .22);
            border-radius: 2mm;
            background: rgba(255, 255, 255, .72);
            text-align: center;
        }
        .score b { display: block; color: var(--navy); font-size: 18pt; font-weight: 800; line-height: 1.2; }
        .score span { display: block; margin-top: .8mm; color: var(--ink-muted); font-size: 9pt; font-weight: 500; }
        .score.hero { border-color: var(--teal); background: var(--teal); box-shadow: 0 1mm 3mm rgba(42, 49, 93, .18); }
        .score.hero b { color: #fff; font-size: 22pt; }
        .score.hero span { color: rgba(255, 255, 255, .85); }

        .criteria {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.8mm 3mm;
            width: 88%;
            margin: 5mm auto 0;
        }
        .criterion {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 2mm;
            padding: 1.6mm 3mm;
            border-inline-start: .9mm solid var(--sand);
            border-radius: 0 1.2mm 1.2mm 0;
            background: rgba(129, 194, 196, .13);
            font-size: 9.5pt;
            font-weight: 500;
        }
        .criterion strong { color: var(--navy); font-weight: 700; white-space: nowrap; }

        .footer {
            margin-top: auto;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 6mm;
            padding-top: 4mm;
            border-top: .35mm solid rgba(42, 49, 93, .18);
        }
        .meta { font-size: 8.5pt; font-weight: 500; line-height: 1.85; color: var(--ink-muted); }
        .meta strong { color: var(--navy); font-weight: 700; }

        .seal {
            flex: none;
            width: 26mm;
            height: 26mm;
            border: .8mm solid var(--sand);
            outline: .3mm solid var(--sand);
            outline-offset: 1.4mm;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .6mm;
            background: rgba(255, 255, 255, .75);
            color: var(--sand-ink);
            text-align: center;
            font-size: 8pt;
            font-weight: 700;
            line-height: 1.25;
        }
        .seal em { font-style: normal; font-size: 7pt; font-weight: 500; color: var(--ink-muted); }

        .verification { display: flex; align-items: center; gap: 3mm; }
        .qr {
            flex: none;
            padding: 1mm;
            border: .35mm solid var(--sand);
            border-radius: 1.5mm;
            background: #fff;
        }
        .qr svg { display: block; width: 22mm; height: 22mm; }
        .verify-text { max-width: 46mm; font-size: 8pt; font-weight: 500; line-height: 1.6; color: var(--ink-muted); }
    </style>
</head>
<body>
<main class="sheet">
    <div class="texture"></div>
    <div class="wash"></div>
    <div class="minaret start"></div>
    <div class="minaret end"></div>

    <section class="frame">
        <span class="corner tr"></span><span class="corner tl"></span>
        <span class="corner br"></span><span class="corner bl"></span>

        <header class="header">
            @if($certificate['result']['is_excellent'])
                <div class="excellence">متميّز</div>
            @endif
            <img src="{{ $asset('logo.png') }}" alt="">
            <div class="identity">
                <div class="issuer">{{ $certificate['issuer_name'] }}</div>
                <div class="program">{{ $certificate['project']['name'] }}</div>
            </div>
        </header>

        <div class="rule"><i></i><b></b><i></i></div>

        <h1 class="title">شهادة نتيجة نهائية</h1>

        <p class="intro">تشهد بأن الطالب</p>
        <div class="student">
            {{ $certificate['student']['name'] }}
            @if($certificate['student']['academic_class'] ?? null)
                <small>{{ $certificate['student']['academic_class'] }}</small>
            @endif
        </div>
        <p class="statement">
            أتم دورة التقييم «{{ $certificate['cycle']['name'] }}» خلال الفترة
            <bdi dir="ltr">{{ $certificate['cycle']['start_date'] }} – {{ $certificate['cycle']['end_date'] }}</bdi>
            واستحق هذه النتيجة بعد اعتماد جميع معايير التقييم.
        </p>

        <div class="scores">
            <div class="score hero">
                <b><bdi dir="ltr">{{ number_format($certificate['result']['final_percentage'], 2) }}%</bdi></b>
                <span>النسبة النهائية مع المكافآت</span>
            </div>
            <div class="score">
                <b>{{ number_format($certificate['result']['final_score'], 2) }}</b>
                <span>المجموع النهائي</span>
            </div>
            <div class="score">
                <b>{{ $certificate['result']['is_excellent'] ? 'متميز' : 'مشارك' }}</b>
                <span>حالة التميز</span>
            </div>
            @if($certificate['result']['rank'])
                <div class="score">
                    <b>{{ $certificate['result']['rank'] }}</b>
                    <span>الترتيب المعتمد</span>
                </div>
            @endif
        </div>

        <div class="criteria">
            @foreach($certificate['criteria'] as $criterion)
                @if($criterion['is_applicable'])
                    <div class="criterion">
                        <span>{{ $criterion['name'] }}</span>
                        <strong><bdi dir="ltr">{{ number_format($criterion['score'], 2) }} / {{ number_format($criterion['maximum_score'], 2) }}</bdi></strong>
                    </div>
                @endif
            @endforeach
        </div>

        <footer class="footer">
            <div class="meta">
                <div>الرقم الذاتي: <strong><bdi dir="ltr">{{ $certificate['student']['selfnumber'] ?: '—' }}</bdi></strong></div>
                <div>رقم الشهادة: <strong><bdi dir="ltr">{{ $certificate['serial_number'] }}</bdi></strong></div>
                <div>تاريخ الإصدار: <strong><bdi dir="ltr">{{ substr($certificate['issued_at'], 0, 10) }}</bdi></strong></div>
            </div>

            <div class="seal">
                نتيجة معتمدة
                <em>ختم الإدارة</em>
            </div>

            <div class="verification">
                <div class="verify-text">امسح الرمز للتحقق من سريان الشهادة، أو استخدم رقمها في النظام.</div>
                <div class="qr">{!! $qrSvg !!}</div>
            </div>
        </footer>
    </section>
</main>
</body>
</html>
