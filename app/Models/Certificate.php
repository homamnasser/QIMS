<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $guarded = [];

    // لا يخرج هذان في استجابات الـ API (issue / issueBatch يعيدان النموذج كاملًا).
    // الإخفاء يطال التسلسل (toArray/toJson) فقط، ولا يمنع الوصول المباشر بالخاصية
    // الذي تعتمده صفحة التحقق ($certificate->data_snapshot).
    // - verification_token_hash: تجزئة رمز التحقق؛ لا داعي لتسريبها.
    // - data_snapshot: نسخة بيانات الطالب الكاملة؛ تُعرض فقط عبر صفحة التحقق العامة.
    protected $hidden = [
        'verification_token_hash',
        'data_snapshot',
    ];

    protected $casts = [
        'data_snapshot' => 'array',
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function result()
    {
        return $this->belongsTo(EvaluationResult::class, 'evaluation_result_id');
    }
}
