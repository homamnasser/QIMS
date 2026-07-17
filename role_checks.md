# سجل شروط فحص الأدوار في الباك إند (Role Checks Registry)

يحتوي هذا الملف على حصر شامل لكافة الملفات والأسطر البرمجية التي يتم فيها فحص صلاحيات المستخدم بناءً على **اسم الدور (Role Name)** الحرفي بدلاً من الصلاحية (Permission) في خادم الباك إند لمشروع QIMS.

---

## 1. الشروط الحرفية المباشرة (hasRole)

تستخدم هذه الأسطر دالة `hasRole` لمطابقة دور معين لمستخدم بشكل حرفي تام:

### أ. ملف [AppServiceProvider.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Providers/AppServiceProvider.php) (السطر 75-77)
* **الكود:**
  ```php
  Gate::before(function ($user, $ability) {
      return $user->hasRole('super-admin') ? true : null;
  });
  ```
* **الغرض:** السماح الكامل والتلقائي للـ `super-admin` بتخطي كافة بوابات الصلاحيات (Bypass) دون الحاجة للتحقق من الصلاحية الفردية.

### ب. ملف [ProjectService.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Services/ProjectService.php) (السطر 98-100)
* **الكود:**
  ```php
  if ($user->hasRole('supervisor')) {
      return Project::where('supervisor', $userId)->get();
  }
  ```
* **الغرض:** فلترة المشاريع المعروضة لتظهر فقط المشاريع التي يشرف عليها المستخدم الحالي إذا كان يحمل دور المشرف `supervisor` حرفياً.

### ج. ملف [SabrController.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Controllers/SabrController.php) (السطر 128-132)
* **الكود:**
  ```php
  if ($user->hasRole('student')) {
      $filters['student'] = $user->id;
  } else {
      $filters['giver'] = $user->id;
  }
  ```
* **الغرض:** فلترة وتوجيه البيانات عند استدعاء اختبارات القرآن (السبر)؛ فإذا كان المستخدم طالباً يُجبر النظام على جلب سجلاته الفردية فقط، وإذا كان موظفاً (معلماً) يجلب السجلات التي قام بتسميعها.

### د. ملف [WarningController.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Controllers/WarningController.php) (السطر 105)
* **الكود:**
  ```php
  $isStudent = $user->hasRole('student');
  ```
* **الغرض:** التحقق مما إذا كان المستخدم طالباً لفلترة الإنذارات المعروضة لتقتصر على إنذاراته الشخصية فقط.

### هـ. ملف [StoreStudentMarkRequest.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Requests/StoreStudentMarkRequest.php) (الأسطر 37 و 82)
* **الكود 1 (السطر 37):**
  ```php
  if ($user && !$user->hasRole('teacher')) {
      $rules['behavior_admin_marks'] = 'required|numeric|min:0';
      ...
  }
  ```
* **الغرض 1:** إلزام إدخال الدرجات الإدارية فقط إذا كان المستخدم الذي يقوم بالرصد ليس معلماً (أي مشرفاً أو مديراً).
* **الكود 2 (السطر 82):**
  ```php
  if (Auth::user()->hasRole('teacher')) {
      $isActualTeacher = DB::table('circles')->where('id', $circleId)->where('teacher_id', $currentUserId)->exists();
      if (!$isActualTeacher) {
          $validator->errors()->add('circle', 'Unauthorized! You are not the assigned teacher...');
      }
  }
  ```
* **الغرض 2:** منع المعلمين من رصد درجات لحلقات لا يدرسونها، بينما يتم تجاوز هذا المنع تلقائياً للمشرفين والمدراء.

---

## 2. شروط المطابقة المرنة للأدوار (Sub-string Matches)

تتحقق هذه الشروط من احتواء اسم الدور على كلمة مفتاحية معينة لتفادي قيود التسمية الصارمة:

### أ. ملف [StoreProjectRequest.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Requests/StoreProjectRequest.php) (السطر 31-34)
* **الكود:**
  ```php
  $hasSupervisorRole = $user && $user->roles()->where(function($q) {
      $q->where('name', 'like', '%supervisor%')
        ->orWhere('name', 'like', '%admin%');
  })->exists();
  ```
* **الغرض:** التحقق من أن الموظف المسند كمشرف للمشروع يمتلك دوراً يحتوي على المقطع `supervisor` أو `admin` (مثل `custom-supervisor`).

### ب. ملف [StoreCourseRequest.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Requests/StoreCourseRequest.php) (السطر 45-50) & [UpdateCourseRequest.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Requests/UpdateCourseRequest.php) (السطر 48-53)
* **الكود:**
  ```php
  ->where(function ($roleQuery) {
      $roleQuery->where('roles.name', 'like', '%supervisor%')
                ->orWhere('roles.name', 'like', '%admin%');
  })
  ```
* **الغرض:** التحقق من أن المشرف المسند للكورس يمتلك دوراً يحتوي على المقطع `supervisor` أو `admin` في قاعدة البيانات.

---

## 3. حماية أدوار النظام الثابتة (System Role Hardcoding)

تمنع هذه الشروط العبث بالأدوار الحيوية للنظام عبر لوحة التحكم:

### أ. ملف [RoleController.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Controllers/RoleController.php) (الأسطر 40, 85, 137)
* **الكود:**
  ```php
  $systemRoles = ['super-admin', 'admin', 'supervisor', 'teacher', 'student'];
  ```
* **الغرض:** حظر عمليات الإنشاء (`createRole`) أو التعديل (`updateRole`) أو الحذف (`deleteRole`) لأي من الأدوار الخمسة الأساسية وإرجاع استجابة `403 Forbidden` لحماية النظام من الأخطاء البشرية.

### ب. ملف [AuthController.php](file:///home/hussam-eldeen/backup-6-Jun-24/graduation/backend/QIMS/app/Http/Controllers/AuthController.php) (السطر 71)
* **الكود:**
  ```php
  $userData = [
      ...
      'role' => 'student'
  ];
  ```
* **الغرض:** تثبيت مسمى الدور كـ `'student'` بشكل صريح في كائن استجابة تسجيل الدخول عند دخول أي طالب لتسهيل فلترة العرض في الواجهة الأمامية.
