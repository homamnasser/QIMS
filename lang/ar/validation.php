<?php

/*
|--------------------------------------------------------------------------
| رسائل التحقق من صحة المدخلات
|--------------------------------------------------------------------------
|
| تستخدم هذه الرسائل تلقائيًا في كل استجابة 422 يصدرها النظام.
| اللغة الاحتياطية هي الإنجليزية، فأي مفتاح غير مترجم هنا يعود إليها؛
| لذلك يجب إبقاء هذا الملف مغطيًا لكل القواعد المستخدمة فعليًا.
|
*/

return [

    'accepted' => 'يجب قبول :attribute.',
    'accepted_if' => 'يجب قبول :attribute عندما يكون :other مساويًا لـ :value.',
    'active_url' => 'يجب أن يكون :attribute رابطًا صالحًا.',
    'after' => 'يجب أن يكون :attribute تاريخًا لاحقًا لـ :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date أو لاحقًا له.',
    'alpha' => 'يجب أن يحتوي :attribute على حروف فقط.',
    'alpha_dash' => 'يجب أن يحتوي :attribute على حروف وأرقام وشرطات فقط.',
    'alpha_num' => 'يجب أن يحتوي :attribute على حروف وأرقام فقط.',
    'any_of' => 'قيمة :attribute غير صالحة.',
    'array' => 'يجب أن تكون قيمة :attribute قائمة.',
    'ascii' => 'يجب أن يحتوي :attribute على محارف إنجليزية ورموز أحادية البايت فقط.',
    'before' => 'يجب أن يكون :attribute تاريخًا سابقًا لـ :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date أو سابقًا له.',
    'between' => [
        'array' => 'يجب أن يحتوي :attribute على عدد عناصر بين :min و:max.',
        'file' => 'يجب أن يكون حجم :attribute بين :min و:max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و:max.',
        'string' => 'يجب أن يكون طول :attribute بين :min و:max حرفًا.',
    ],
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'can' => 'يحتوي :attribute على قيمة غير مصرح بها.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'contains' => 'ينقص :attribute قيمة مطلوبة.',
    'current_password' => 'كلمة المرور الحالية غير صحيحة.',
    'date' => 'يجب إدخال تاريخ صالح في حقل :attribute.',
    'date_equals' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date.',
    'date_format' => 'يجب أن يطابق :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي :attribute على :decimal منزلة عشرية.',
    'declined' => 'يجب رفض :attribute.',
    'declined_if' => 'يجب رفض :attribute عندما يكون :other مساويًا لـ :value.',
    'different' => 'يجب أن يختلف :attribute عن :other.',
    'digits' => 'يجب أن يتكون :attribute من :digits رقمًا.',
    'digits_between' => 'يجب أن يتكون :attribute من عدد أرقام بين :min و:max.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => 'يحتوي :attribute على قيمة مكررة.',
    'doesnt_contain' => 'يجب ألا يحتوي :attribute على أي من القيم التالية: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي :attribute بأي من القيم التالية: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ :attribute بأي من القيم التالية: :values.',
    'email' => 'يجب إدخال بريد إلكتروني صالح في حقل :attribute.',
    'encoding' => 'يجب أن يكون ترميز :attribute هو :encoding.',
    'ends_with' => 'يجب أن ينتهي :attribute بإحدى القيم التالية: :values.',
    'enum' => 'قيمة :attribute المختارة غير صالحة.',
    'exists' => 'قيمة :attribute المختارة غير موجودة في النظام.',
    'extensions' => 'يجب أن يكون امتداد ملف :attribute أحد التالي: :values.',
    'file' => 'يجب إرفاق ملف في حقل :attribute.',
    'filled' => 'يجب ألا يكون :attribute فارغًا.',
    'gt' => [
        'array' => 'يجب أن يحتوي :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'string' => 'يجب أن يكون طول :attribute أكبر من :value حرفًا.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي :attribute على :value عنصرًا على الأقل.',
        'file' => 'يجب أن يكون حجم :attribute :value كيلوبايت على الأقل.',
        'numeric' => 'يجب أن تكون قيمة :attribute :value أو أكثر.',
        'string' => 'يجب أن يكون طول :attribute :value حرفًا على الأقل.',
    ],
    'hex_color' => 'يجب أن يكون :attribute لونًا بصيغة ست عشرية صالحة.',
    'image' => 'يجب إرفاق صورة في حقل :attribute.',
    'in' => 'قيمة :attribute المختارة غير مسموح بها.',
    'in_array' => 'يجب أن تكون قيمة :attribute موجودة ضمن :other.',
    'in_array_keys' => 'يجب أن يحتوي :attribute على أحد المفاتيح التالية: :values.',
    'integer' => 'يجب إدخال رقم صحيح في حقل :attribute.',
    'ip' => 'يجب أن يكون :attribute عنوان IP صالحًا.',
    'ipv4' => 'يجب أن يكون :attribute عنوان IPv4 صالحًا.',
    'ipv6' => 'يجب أن يكون :attribute عنوان IPv6 صالحًا.',
    'json' => 'يجب أن يكون :attribute نصًا بصيغة JSON صالحة.',
    'list' => 'يجب أن يكون :attribute قائمة مرتبة.',
    'lowercase' => 'يجب أن يكون :attribute بحروف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي :attribute على أقل من :value عنصرًا.',
        'file' => 'يجب أن يكون حجم :attribute أصغر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أصغر من :value.',
        'string' => 'يجب أن يكون طول :attribute أصغر من :value حرفًا.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :value عنصرًا.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :value أو أقل.',
        'string' => 'يجب ألا يتجاوز طول :attribute :value حرفًا.',
    ],
    'mac_address' => 'يجب أن يكون :attribute عنوان MAC صالحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصرًا.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
        'string' => 'يجب ألا يتجاوز طول :attribute :max حرفًا.',
    ],
    'max_digits' => 'يجب ألا يتجاوز عدد أرقام :attribute :max رقمًا.',
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصرًا على الأقل.',
        'file' => 'يجب أن يكون حجم :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب أن يكون طول :attribute :min حرفًا على الأقل.',
    ],
    'min_digits' => 'يجب أن يتكون :attribute من :min رقمًا على الأقل.',
    'missing' => 'يجب ألا يُرسل حقل :attribute.',
    'missing_if' => 'يجب ألا يُرسل حقل :attribute عندما يكون :other مساويًا لـ :value.',
    'missing_unless' => 'يجب ألا يُرسل حقل :attribute إلا إذا كان :other مساويًا لـ :value.',
    'missing_with' => 'يجب ألا يُرسل حقل :attribute مع :values.',
    'missing_with_all' => 'يجب ألا يُرسل حقل :attribute مع :values.',
    'multiple_of' => 'يجب أن تكون قيمة :attribute من مضاعفات :value.',
    'not_in' => 'قيمة :attribute المختارة غير مسموح بها.',
    'not_regex' => 'صيغة :attribute غير صالحة.',
    'numeric' => 'يجب إدخال رقم في حقل :attribute.',
    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت :attribute في تسريب بيانات معروف؛ يرجى اختيار كلمة مرور أخرى.',
    ],
    'present' => 'يجب إرسال حقل :attribute.',
    'present_if' => 'يجب إرسال حقل :attribute عندما يكون :other مساويًا لـ :value.',
    'present_unless' => 'يجب إرسال حقل :attribute إلا إذا كان :other مساويًا لـ :value.',
    'present_with' => 'يجب إرسال حقل :attribute مع :values.',
    'present_with_all' => 'يجب إرسال حقل :attribute مع :values.',
    'prohibited' => 'حقل :attribute غير مسموح به.',
    'prohibited_if' => 'حقل :attribute غير مسموح به عندما يكون :other مساويًا لـ :value.',
    'prohibited_if_accepted' => 'حقل :attribute غير مسموح به عند قبول :other.',
    'prohibited_if_declined' => 'حقل :attribute غير مسموح به عند رفض :other.',
    'prohibited_unless' => 'حقل :attribute غير مسموح به إلا إذا كان :other ضمن :values.',
    'prohibits' => 'يمنع حقل :attribute إرسال :other.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي :attribute على المفاتيح التالية: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other مساويًا لـ :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب إلا إذا كان :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند إرسال :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند إرسال :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم إرسال :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم إرسال أي من :values.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عنصرًا بالضبط.',
        'file' => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string' => 'يجب أن يكون طول :attribute :size حرفًا.',
    ],
    'starts_with' => 'يجب أن يبدأ :attribute بإحدى القيم التالية: :values.',
    'string' => 'يجب إدخال نص في حقل :attribute.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صالحة.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',
    'uploaded' => 'فشل رفع ملف :attribute.',
    'uppercase' => 'يجب أن يكون :attribute بحروف كبيرة.',
    'url' => 'يجب أن يكون :attribute رابطًا صالحًا.',
    'ulid' => 'يجب أن يكون :attribute معرف ULID صالحًا.',
    'uuid' => 'يجب أن يكون :attribute معرف UUID صالحًا.',

    /*
    |--------------------------------------------------------------------------
    | رسائل مخصصة لحقول بعينها
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'preview' => [
            'required' => 'يجب تحديد ما إذا كان الاحتساب معاينة أم احتسابًا نهائيًا.',
        ],
        'course_ids' => [
            'required' => 'يجب اختيار مقرر واحد على الأقل لدورة التقييم.',
            'min' => 'يجب اختيار مقرر واحد على الأقل لدورة التقييم.',
        ],
        'periods' => [
            'required' => 'يجب تعريف فترة تقييم واحدة على الأقل.',
            'min' => 'يجب تعريف فترة تقييم واحدة على الأقل.',
        ],
        'reason' => [
            'required' => 'يجب توضيح سبب إلغاء الشهادة.',
            'min' => 'سبب الإلغاء قصير جدًا؛ اكتب خمسة أحرف على الأقل.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول بالعربية
    |--------------------------------------------------------------------------
    |
    | تستبدل هذه الأسماء بالرمز :attribute داخل كل رسالة، فتظهر الرسالة
    | للمستخدم باسم الحقل كما يراه في الشاشة لا باسمه البرمجي.
    |
    */

    'attributes' => [
        // دورة التقييم
        'project_id' => 'المشروع',
        'policy_id' => 'سياسة الاحتساب',
        'name' => 'الاسم',
        'season' => 'الموسم',
        'status' => 'الحالة',
        'top_students_count' => 'عدد الطلاب المكرمين',
        'course_ids' => 'المقررات',
        'course_ids.*' => 'المقرر',
        'course_id' => 'المقرر',
        'rule_configuration' => 'إعدادات القواعد',
        'periods' => 'فترات التقييم',
        'periods.*.name' => 'اسم الفترة',
        'periods.*.sequence' => 'ترتيب الفترة',
        'periods.*.start_date' => 'تاريخ بداية الفترة',
        'periods.*.end_date' => 'تاريخ نهاية الفترة',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',

        // مدخلات التقييم
        'cycle_id' => 'دورة التقييم',
        'evaluation_period_id' => 'فترة التقييم',
        'circle_id' => 'الحلقة',
        'candidate_id' => 'الطالب المرشح',
        'behavior_score' => 'درجة السلوك',
        'participation_score' => 'درجة المشاركة والواجبات',
        'teacher_opinion_score' => 'درجة رأي المدرس',
        'comments' => 'الملاحظات',
        'notes' => 'الملاحظات',
        'below_minimum' => 'إشارة «دون الحد الأدنى»',

        // الاحتساب والشهادات
        'preview' => 'نوع الاحتساب',
        'reason' => 'السبب',
        'event_type' => 'نوع الحدث',
        'actor_id' => 'المستخدم المنفذ',
        'from' => 'من تاريخ',
        'to' => 'إلى تاريخ',

        // عام
        'search' => 'كلمة البحث',
        'page' => 'رقم الصفحة',
        'per_page' => 'عدد العناصر في الصفحة',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'selfnumber' => 'الرقم الذاتي',
        'first_name' => 'الاسم الأول',
        'last_name' => 'الكنية',
        'phone' => 'رقم الهاتف',
        'date' => 'التاريخ',
        'type' => 'النوع',
        'mark' => 'الدرجة',
        'title' => 'العنوان',
        'description' => 'الوصف',
    ],

];
