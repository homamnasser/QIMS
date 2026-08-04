@php
    /** @var bool $valid */
    /** @var array|null $certificate */
    $issuer = config('evaluation.certificate.issuer_name');
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#2a315d">
    <title>{{ $valid ? 'شهادة سارية' : 'تعذر التحقق' }} — {{ $issuer }}</title>
    <style>
        @font-face { font-family: "Tajawal"; font-weight: 400; font-display: swap; src: url("{{ asset('brand/fonts/Tajawal-Regular.ttf') }}") format("truetype"); }
        @font-face { font-family: "Tajawal"; font-weight: 700; font-display: swap; src: url("{{ asset('brand/fonts/Tajawal-Bold.ttf') }}") format("truetype"); }
        @font-face { font-family: "Tajawal"; font-weight: 800; font-display: swap; src: url("{{ asset('brand/fonts/Tajawal-ExtraBold.ttf') }}") format("truetype"); }

        :root {
            color-scheme: light;
            --navy: #2a315d;
            --teal: #458ca2;
            --sand: #debd87;
            --sand-ink: #9b6c1d;
            --ivory: #fbf9f5;
            --ink: #1d1d1d;
            --ink-muted: #5d6472;
            --line: rgba(42, 49, 93, .14);
            --ok: #2f7d68;
            --bad: #a0392d;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            padding-bottom: 32px;
            background: var(--ivory);
            color: var(--ink);
            font-family: "Tajawal", "Segoe UI", system-ui, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            -webkit-text-size-adjust: 100%;
        }

        .page { max-width: 480px; margin: 0 auto; padding: 0 16px; }

        /* ترويسة بلون الهوية مع زخرفة المئذنة نفسها المستخدمة في الشهادة. */
        .masthead {
            position: relative;
            margin: 0 0 -44px;
            padding: 28px 16px 60px;
            border-radius: 0 0 22px 22px;
            background: linear-gradient(155deg, var(--navy), #3c4783 70%, var(--teal));
            color: #fff;
            text-align: center;
            overflow: hidden;
        }
        .masthead::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: -14px;
            width: 86px;
            height: 190px;
            background: #fff;
            opacity: .09;
            -webkit-mask: url("{{ asset('brand/minaret.png') }}") no-repeat left bottom / contain;
            mask: url("{{ asset('brand/minaret.png') }}") no-repeat left bottom / contain;
        }
        .masthead > * { position: relative; z-index: 1; }
        .masthead img { width: 60px; height: 60px; object-fit: contain; }
        .masthead h1 { margin: 8px 0 2px; font-size: 1.05rem; font-weight: 800; letter-spacing: -.2px; }
        .masthead p { margin: 0; font-size: .8rem; font-weight: 400; color: rgba(255, 255, 255, .78); }

        .card {
            position: relative;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 6px 24px rgba(42, 49, 93, .1);
        }

        .status { padding: 24px 20px 20px; text-align: center; }
        .status .mark {
            display: grid;
            place-items: center;
            width: 60px;
            height: 60px;
            margin: 0 auto 12px;
            border-radius: 50%;
            font-size: 1.9rem;
            line-height: 1;
        }
        .status.is-ok .mark { background: rgba(47, 125, 104, .12); color: var(--ok); }
        .status.is-bad .mark { background: rgba(160, 57, 45, .1); color: var(--bad); }
        .status h2 { margin: 0; font-size: 1.35rem; font-weight: 800; }
        .status.is-ok h2 { color: var(--ok); }
        .status.is-bad h2 { color: var(--bad); }
        .status p { margin: 6px 0 0; font-size: .88rem; color: var(--ink-muted); }

        /* الفاصل المعيّن: العنصر الزخرفي نفسه المستخدم في الشهادة. */
        .rule { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0 20px; }
        .rule i { flex: 1; height: 1px; background: linear-gradient(90deg, transparent, var(--sand)); }
        .rule i:last-child { background: linear-gradient(270deg, transparent, var(--sand)); }
        .rule b { width: 7px; height: 7px; background: var(--sand); transform: rotate(45deg); }

        dl { margin: 0; padding: 8px 20px 20px; }
        .row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            padding: 11px 0;
            border-bottom: 1px solid var(--line);
        }
        .row:last-child { border-bottom: 0; }
        dt { flex: none; font-size: .82rem; font-weight: 400; color: var(--ink-muted); }
        dd { margin: 0; font-size: .95rem; font-weight: 700; color: var(--navy); text-align: end; overflow-wrap: anywhere; }
        dd.num { direction: ltr; font-variant-numeric: tabular-nums; }
        dd.code {
            direction: ltr;
            font-family: ui-monospace, "SFMono-Regular", Menlo, monospace;
            font-size: .78rem;
            font-weight: 400;
            letter-spacing: -.2px;
        }

        .row.stack { flex-direction: column; align-items: stretch; gap: 4px; }
        .row.stack dd { text-align: start; font-weight: 400; color: var(--ink-muted); }

        .headline { display: block; margin: 2px 0 0; color: var(--teal); font-size: 1.2rem; font-weight: 800; line-height: 1.35; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 11px;
            border: 1px solid var(--sand);
            border-radius: 999px;
            background: rgba(222, 189, 135, .16);
            color: var(--sand-ink);
            font-size: .8rem;
            font-weight: 700;
        }
        .badge::before { content: "★"; }

        .hint {
            margin: 16px 0 0;
            padding: 0 4px;
            font-size: .78rem;
            line-height: 1.75;
            color: var(--ink-muted);
            text-align: center;
        }
        .hint strong { color: var(--navy); font-weight: 700; }

        /* تنبيه المطابقة: يُجبر المدقّق على مقارنة الاسم والرقم مع الورقة التي بين يديه،
           لأن سريان الرمز وحده لا يمنع نسخه ولصقه على شهادة مزوّرة. */
        .compare {
            margin: 14px 20px 4px;
            padding: 12px 14px;
            border: 1px solid var(--sand);
            border-radius: 12px;
            background: rgba(222, 189, 135, .16);
            color: var(--sand-ink);
            font-size: .84rem;
            font-weight: 700;
            line-height: 1.7;
            text-align: center;
        }
        .compare::before { content: "⚠ "; }
        .compare span { display: block; margin-top: 4px; font-weight: 400; color: var(--ink); }
    </style>
</head>
<body>

<header class="masthead">
    <img src="{{ asset('brand/logo.png') }}" alt="">
    <h1>{{ $issuer }}</h1>
    <p>التحقق من الشهادات</p>
</header>

<main class="page">
    @if($valid)
        <div class="card">
            <div class="status is-ok">
                <div class="mark" aria-hidden="true">✓</div>
                <h2>الرمز يقابل شهادة سارية</h2>
                <p>يعني هذا أن الرمز صحيح فقط؛ تأكّد أدناه أن بياناته تطابق الورقة التي بين يديك.</p>
            </div>

            <div class="compare">
                طابِق الاسم ورقم الشهادة أدناه مع المطبوع على الشهادة التي بين يديك.
                <span>إن اختلف أيٌّ منهما فالشهادة الورقية مزوّرة وإن كان الرمز صحيحًا.</span>
            </div>

            <div class="rule"><i></i><b></b><i></i></div>

            <dl>
                <div class="row stack">
                    <dt>الطالب</dt>
                    <dd><span class="headline">{{ $certificate['student_name'] }}</span></dd>
                </div>
                <div class="row">
                    <dt>البرنامج</dt>
                    <dd>{{ $certificate['project_name'] }}</dd>
                </div>
                <div class="row">
                    <dt>دورة التقييم</dt>
                    <dd>{{ $certificate['cycle_name'] }}</dd>
                </div>
                <div class="row">
                    <dt>المجموع النهائي</dt>
                    <dd class="num">{{ number_format($certificate['final_score'], 2) }}</dd>
                </div>
                @if($certificate['rank'])
                    <div class="row">
                        <dt>الترتيب المعتمد</dt>
                        <dd class="num">{{ $certificate['rank'] }}</dd>
                    </div>
                @endif
                <div class="row">
                    <dt>حالة التميز</dt>
                    <dd>
                        @if($certificate['is_excellent'])
                            <span class="badge">متميّز</span>
                        @else
                            مشارك
                        @endif
                    </dd>
                </div>
                <div class="row">
                    <dt>رقم الشهادة</dt>
                    <dd class="code">{{ $certificate['serial_number'] }}</dd>
                </div>
                <div class="row">
                    <dt>تاريخ الإصدار</dt>
                    <dd class="num">{{ \Illuminate\Support\Carbon::parse($certificate['issued_at'])->toDateString() }}</dd>
                </div>
                <div class="row stack">
                    <dt>البصمة الرقمية للملف (SHA-256)</dt>
                    <dd class="code">{{ $certificate['file_sha256'] }}</dd>
                </div>
            </dl>
        </div>

        <p class="hint">
            قُرئت هذه البيانات مباشرة من سجلات <strong>{{ $issuer }}</strong> لحظة فتح الرابط،
            فهي المرجع لا ما هو مطبوع على الورقة. إن لم يتطابقا فاعتمد ما يظهر هنا وراجع
            <strong>{{ $issuer }}</strong>.
        </p>
    @else
        <div class="card">
            <div class="status is-bad">
                <div class="mark" aria-hidden="true">!</div>
                <h2>تعذر التحقق</h2>
                <p>رمز التحقق غير صالح أو لا يقابل شهادة سارية.</p>
            </div>

            <div class="rule"><i></i><b></b><i></i></div>

            <dl>
                <div class="row stack">
                    <dt>الأسباب المحتملة</dt>
                    <dd>
                        نُسخ الرابط ناقصًا أو أُعيدت كتابته يدويًا،
                        أو أن الشهادة أُلغيت أو استُبدلت بإصدار أحدث.
                    </dd>
                </div>
            </dl>
        </div>

        <p class="hint">
            امسح الرمز مرة أخرى من الشهادة الأصلية، أو راجع
            <strong>{{ $issuer }}</strong> مستشهدًا برقم الشهادة المطبوع عليها.
        </p>
    @endif
</main>

</body>
</html>
