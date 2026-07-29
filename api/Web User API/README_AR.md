# مجموعة Bruno لمستخدم لوحة الويب

تغطي هذه المجموعة 139 طلبًا: 133 مسارًا محميًا
بجلسة الويب، ومسارات المصادقة، ومسارات الاستبيان العامة. يوجد 75
طلبًا يغيّر البيانات أو قد يحذفها.

## ترتيب التشغيل

1. اختر بيئة `Local`.
2. شغّل `Authentication/01 Initialize CSRF Cookies`.
3. شغّل `Authentication/02 Login as Web User`.
4. تحقق من الجلسة عبر `Authentication/03 Get Current Web User`.
5. شغّل طلبات القراءة أو التعديل المطلوبة.
6. أنهِ الجلسة عبر `Authentication/04 Logout Web User`.

تحتفظ Bruno تلقائيًا بـCookie الجلسة. طلب تهيئة CSRF يقرأ Cookie العامة
`XSRF-TOKEN` ويحفظ قيمتها في متغير البيئة `xsrfToken`، ثم ترسل طلبات
POST وPUT وPATCH وDELETE الترويسة `X-XSRF-TOKEN`.

## تنبيه

طلبات الإنشاء والتعديل والحذف ليست Smoke Tests آمنة على بيانات مهمة. القيم
الافتراضية مناسبة لبيانات `TestDataSeeder` المحلية، ويجب مراجعتها قبل
إرسال الطلب. لا تستخدم `migrate:fresh --seed` على قاعدة إنتاج.
