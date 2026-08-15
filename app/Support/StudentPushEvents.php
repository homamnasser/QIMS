<?php

namespace App\Support;

use App\Models\Exam;
use App\Models\Memorization;
use App\Models\Note;
use App\Models\ReadingImprovement;
use App\Models\Sabr;
use App\Models\StudentCourseAbsence;
use App\Models\Warning;

/**
 * ملف واحد يحمل كل قرار «أي فعل يُشعر بماذا».
 *
 * صنف PHP لا ملف config عمداً: ملفات الإعداد تنكسر تحت config:cache إن احتاجت
 * closure يوماً، وهذه الخريطة ستكبر.
 */
class StudentPushEvents
{
    /** موديلات يُشعر إنشاؤها الطالب فورًا. */
    public const ON_CREATE = [
        Warning::class,
        Note::class,
        Sabr::class,
        // مسار التسميع الوحيد اليوم (MemorizationController::createMemorization)
        // يكتم هذا الحدث عمدًا ويرسل إشعارًا واحدًا عن النطاق كلّه، وإلا صار
        // تسميع عشر صفحات عشرة إشعارات. يبقى المدخل هنا لأنّ أي مسار إنشاء
        // مستقبلي لصفحة مفردة يستحق إشعارًا — فإن أضفت مسارًا يكتب نطاقًا،
        // اكتم الحدث فيه أنت أيضًا.
        Memorization::class,
        Exam::class,
        ReadingImprovement::class,
        StudentCourseAbsence::class,
    ];

    /**
     * موديل => العمود (أو الأعمدة) الذي يعني تغيّره نتيجة جديدة تهم الطالب.
     *
     * القيمة تُمرَّر كما هي إلى wasChanged التي تقبل نصًّا ومصفوفة معًا،
     * وتُرجع true إن تغيّر أيٌّ من أعمدة المصفوفة.
     *
     * ⚠️ العمود هنا يجب أن يكون عمودًا يكتبه مسار الحفظ فعلًا. راقبنا سابقًا
     * final_score في ReadingImprovement وهو عمود لا يمسّه نموذج الموظف إطلاقًا
     * (UpdateReadingImprovementRequest يقبل type و occurred_at و description
     * فقط)، فلم يصل الطالب إشعار تعديل قط.
     */
    public const ON_UPDATE = [
        Sabr::class => 'value',
        Exam::class => 'mark',
        ReadingImprovement::class => 'type',
        // تصحيح سجل حضور قائم يهمّ الطالب بقدر تسجيله أول مرة: تحويل حضور إلى
        // غياب، أو تغيير نوع الغياب، أو اعتماد عذر له.
        StudentCourseAbsence::class => ['type', 'is_excused'],
    ];

    /**
     * أيقونة شريط الإشعارات وقناته لكل وجهة.
     *
     * `icon` اسم drawable في التطبيق بلا امتداد، ووجوده شرط: أندرويد لا يفشل إن
     * غاب المورد بل يرسم أيقونة التطبيق مربعاً رمادياً، فيبدو كل إشعار متطابقاً.
     *
     * `channel_id` معرّف قناة يُنشئها التطبيق في `LocalNotifications.channels`،
     * وهي ما يتيح للطالب أن يكتم التسميع ويُبقي الإنذارات من إعدادات أندرويد
     * نفسها. ⚠️ أندرويد **يُسقط** إشعاراً يشير إلى قناة غير موجودة على الجهاز،
     * فأي معرّف يُضاف هنا يجب أن يُضاف هناك أولاً.
     *
     * الوجهة هي المفتاح لأنها موجودة أصلاً في data ولا تحتاج تغيير توقيع describe.
     */
    private const ROUTE_NOTIFICATION = [
        '/warnings' => ['icon' => 'ic_notif_warning', 'channel_id' => 'warnings'],
        '/notes' => ['icon' => 'ic_notif_note', 'channel_id' => 'learning'],
        '/attendance' => ['icon' => 'ic_notif_attendance', 'channel_id' => 'attendance'],
        '/exams' => ['icon' => 'ic_notif_exam', 'channel_id' => 'results'],
        '/final-results' => ['icon' => 'ic_notif_exam', 'channel_id' => 'results'],
        '/memorization' => ['icon' => 'ic_notif_quran', 'channel_id' => 'learning'],
        '/sabr' => ['icon' => 'ic_notif_quran', 'channel_id' => 'learning'],
        '/reading-improvements' => ['icon' => 'ic_notif_quran', 'channel_id' => 'learning'],
    ];

    /**
     * @return array{icon: string, channel_id: string} إعدادات android.notification لهذه الوجهة.
     */
    public static function androidNotification(?string $route): array
    {
        return self::ROUTE_NOTIFICATION[$route]
            ?? ['icon' => 'ic_notif_default', 'channel_id' => 'general'];
    }

    /** نصوص أنواع تحسّن القراءة كما يعرضها التطبيق حرفيًا. */
    private const READING_LABELS = [
        'significant_improvement' => 'تحسن معتبر',
        'slight_improvement' => 'تحسن بسيط',
        'no_improvement' => 'عدم تحسن',
        'decline' => 'تراجع',
    ];

    /**
     * ponytail: النص عربي ثابت في الخادم. لو لزم تعدد اللغات لاحقًا فأرسل
     * data-only وابنِ النص من ملفات الترجمة داخل التطبيق.
     *
     * @return array{0: int|null, 1: string, 2: string, 3: string}|null
     *                                                                  [studentId, title, body, route] أو null إن كان السجل لا يستحق إشعاراً.
     */
    public static function describe(object $record): ?array
    {
        return match ($record::class) {
            Warning::class => [$record->student, 'إنذار جديد', $record->title, '/warnings'],
            Note::class => [$record->student_id, 'ملاحظة جديدة', $record->title, '/notes'],
            Sabr::class => [$record->student, 'سبر جديد', "سبر {$record->type}", '/sabr'],
            Memorization::class => [$record->student, 'تسميع جديد', "صفحة {$record->page_number}", '/memorization'],
            Exam::class => [$record->student, 'علامة امتحان جديدة', "العلامة: {$record->mark}", '/exams'],
            ReadingImprovement::class => [
                $record->student,
                'تقييم قراءة جديد',
                // النوع لا final_score: الأخير يخصّ منظومة التقييم النهائي ولا
                // يكتبه نموذج الموظف، فكان النص يصل فارغًا: «النتيجة: ».
                self::READING_LABELS[$record->type] ?? 'سُجّل لك تقييم قراءة',
                '/reading-improvements',
            ],
            StudentCourseAbsence::class => self::describeAttendance($record),
            default => null,
        };
    }

    /**
     * عمود type في student_course_absences يحمل 'present' أيضاً: الجدول سجل
     * حضور لا سجل غياب. إشعار من حضر بأنه غائب خطأ يراه الطالب فوراً، فصف
     * الحضور لا يُشعر أصلاً.
     */
    private static function describeAttendance(StudentCourseAbsence $record): ?array
    {
        if ($record->type === 'present') {
            return null;
        }

        $body = match ($record->type) {
            'first_period' => 'غياب الحصة الأولى',
            'second_period' => 'غياب الحصة الثانية',
            default => 'غياب يوم كامل',
        };

        // النص يظهر على شاشة القفل، فيبقى قصيراً بلا تفاصيل زائدة (راجع §12).
        return [
            $record->student,
            $record->is_excused ? 'تسجيل غياب مبرَّر' : 'تسجيل غياب',
            $body,
            '/attendance',
        ];
    }
}
