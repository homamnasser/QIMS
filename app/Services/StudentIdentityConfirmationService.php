<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * تأكيد هوية إضافي يقفل قسم النتيجة النهائية بأكمله.
 *
 * رمز الجلسة وحده لا يكفي لفتح القسم: الجهاز قد يبقى مفتوحاً بيد غير صاحبه.
 * لذلك يعيد الطالب إدخال معرّفه وكلمة مروره عند دخول القسم، فيُمنح إذن قصير
 * العمر مرتبط برمز الوصول الحالي وحده.
 */
class StudentIdentityConfirmationService
{
    /**
     * عمر الإذن قصير عمداً: يكفي لجولة القائمة ثم التفاصيل ثم تحميل الشهادة،
     * ولا يترك القسم مفتوحاً بعد أن يترك الطالب جهازه. التطبيق يقفل القسم فور
     * الخروج منه أو انتقاله للخلفية، وهذا السقف يحمي الحالة التي لا يصل فيها
     * إشعار الخروج (إنهاء مفاجئ للتطبيق مثلاً).
     */
    private const GRANT_MINUTES = 5;

    /**
     * تتم المقارنة مع أعمدة الطالب المصادق عليه نفسه، لا ببحث في جدول الطلاب.
     * لذلك لا يفتح معرّف طالب آخر قسم هذا الحساب، ولا تتحول الواجهة إلى وسيلة
     * لتخمين معرّفات الطلاب.
     *
     * الرقم الذاتي يقبله من يملكه فقط؛ والطالب غير الملتحق بأي حلقة لا رقم ذاتي
     * له، فيؤكد باسم المستخدم وحده ولا يُحرم من نتيجته.
     */
    public function matchesOwnIdentity(
        Student $student,
        string $identifier,
        string $password
    ): bool {
        $identifier = trim($identifier);

        // اسم المستخدم مخزّن بأحرف صغيرة، ورمز المسجد داخل الرقم الذاتي بأحرف
        // كبيرة، فنوحّد الحالة بدل الاتكال على ترتيب مقارنة الجدول.
        $matchesIdentifier = hash_equals(
            strtolower((string) $student->username),
            strtolower($identifier)
        ) || (
            $student->selfnumber !== null
            && hash_equals(
                strtoupper($student->selfnumber),
                strtoupper($identifier)
            )
        );

        // كلمة المرور تُفحص دائماً حتى لو فشل المعرّف، حتى لا يكشف فرق زمن
        // الاستجابة أي الحقلين كان الخطأ.
        $matchesPassword = Hash::check($password, $student->password);

        return $matchesIdentifier && $matchesPassword;
    }

    public function grant(Student $student): int
    {
        $key = $this->cacheKey($student);
        if ($key === null) {
            return 0;
        }

        Cache::put($key, true, now()->addMinutes(self::GRANT_MINUTES));

        return self::GRANT_MINUTES * 60;
    }

    public function isConfirmed(Student $student): bool
    {
        $key = $this->cacheKey($student);

        return $key !== null && Cache::get($key) === true;
    }

    public function revoke(Student $student): void
    {
        $key = $this->cacheKey($student);
        if ($key !== null) {
            Cache::forget($key);
        }
    }

    /**
     * الإذن مرتبط برمز الوصول لا بالحساب: تأكيد الهوية على جهاز لا يفتح القسم
     * على جهاز آخر يحمل جلسة أخرى للطالب نفسه.
     */
    private function cacheKey(Student $student): ?string
    {
        $token = $student->currentAccessToken();

        return $token instanceof PersonalAccessToken
            ? 'student-identity-confirmed:'.$student->getKey().':'.$token->getKey()
            : null;
    }
}
