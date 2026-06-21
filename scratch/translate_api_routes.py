# -*- coding: utf-8 -*-
import re

translations = {
    'login': 'تسجيل الدخول',
    'logout': 'تسجيل الخروج',
    'createStaffMember': 'إنشاء موظف',
    'updateStaffMember': 'تعديل موظف',
    'getAllRoles': 'عرض كافة الأدوار',
    'createRole': 'إنشاء دور',
    'getRole': 'عرض تفاصيل الدور',
    'updateRole': 'تعديل الدور',
    'deleteRole': 'حذف الدور',
    'getAllProjects': 'عرض كافة المشاريع',
    'createProject': 'إنشاء مشروع',
    'getProject': 'عرض تفاصيل المشروع',
    'updateProject': 'تعديل المشروع',
    'deleteProject': 'حذف المشروع',
    'getAllPermissions': 'عرض كافة الصلاحيات',
    'editProjectStatus': 'تعديل حالة المشروع',
    'getAllMosques': 'عرض كافة المساجد',
    'createMosque': 'إنشاء مسجد',
    'updateMosque': 'تعديل مسجد',
    'deleteMosque': 'حذف مسجد',
    'getMosque': 'عرض تفاصيل المسجد',
    'getAllStaff': 'عرض كافة الموظفين',
    'getStaffById': 'عرض تفاصيل الموظف',
    'deleteStaff': 'حذف موظف',
    'getAllCourses': 'عرض كافة الكورسات',
    'createCourse': 'إنشاء كورس',
    'getCourse': 'عرض تفاصيل الكورس',
    'updateCourse': 'تعديل الكورس',
    'deleteCourse': 'حذف كورس', # Note: 'deleteCourse' was 'deleteCourse'
    'editCourseStatus': 'تعديل حالة الكورس',
    'createSubject': 'إنشاء مادة',
    'getAllSubjects': 'عرض كافة المواد',
    'getSubject': 'عرض تفاصيل المادة',
    'updateSubject': 'تعديل المادة',
    'deleteSubject': 'حذف مادة',
    'createLesson': 'إنشاء درس',
    'getAllLessons': 'عرض كافة الدروس',
    'getLesson': 'عرض تفاصيل الدرس',
    'updateLesson': 'تعديل الدرس',
    'deleteLesson': 'حذف درس',
    'createCourseDate': 'إنشاء تاريخ الكورس',
    'getDatesByCourse': 'عرض تواريخ الكورس',
    'deleteDate': 'حذف تاريخ',
    'createDateManual': 'إضافة تاريخ يدوياً',
    'assignLessonsToDate': 'إسناد الدروس للتاريخ',
    'updateAssignLessonsToDate': 'تعديل إسناد دروس التاريخ',
    'deleteLessonsFromDate': 'حذف دروس التاريخ',
    'getLessonsByDate': 'عرض دروس التاريخ',
    'getCurriculumByCourse': 'عرض المنهج الدراسي للكورس',
    'createCircle': 'إنشاء حلقة',
    'getMyCircleCurriculum': 'عرض منهج حلقتي',
    'getCircleById': 'عرض تفاصيل الحلقة',
    'getAllCircles': 'عرض كافة الحلقات',
    'updateCircle': 'تعديل الحلقة',
    'deleteCircle': 'حذف الحلقة',
    'getAllStudents': 'عرض كافة الطلاب',
    'getStudentById': 'عرض تفاصيل الطالب',
    'createStudent': 'إنشاء طالب',
    'updateStudent': 'تعديل الطالب',
    'deleteStudent': 'حذف الطالب',
    'addStudentsToCircle': 'إضافة طلاب للحلقة',
    'removeStudentFromCircle': 'إلغاء التحاق طالب بالحلقة',
    'getStudentsByCircle': 'عرض طلاب الحلقة',
    'createNote': 'إنشاء ملاحظة',
    'getNotesByStudentId': 'عرض ملاحظات الطالب',
    'getMyNotes': 'عرض ملاحظاتي',
    'deleteNote': 'حذف ملاحظة',
    'createSabr': 'إنشاء سبر',
    'getSabrByStudentId': 'عرض سبر الطالب',
    'getMySabr': 'عرض سبري',
    'deleteSabr': 'حذف سبر',
    'updateSabrResult': 'تعديل نتيجة السبر',
    'getAllSabrs': 'عرض كافة السبور',
    'createMemorization': 'إنشاء تسميع',
    'getMemorizationsByStudentId': 'عرض تسميع الطالب',
    'getMyMemorizations': 'عرض تسميعاتي',
    'deleteMemorization': 'حذف تسميع',
    'getAllMemorizations': 'عرض كافة التسميعات',
    'createWarning': 'إنشاء إنذار',
    'getWarningById': 'عرض تفاصيل الإنذار',
    'deleteWarning': 'حذف إنذار',
    'getAllWarnings': 'عرض كافة الإنذارات',
    'getMyWarnings': 'عرض إنذاراتي',
    'createExam': 'إنشاء امتحان',
    'getExamById': 'عرض تفاصيل الامتحان',
    'deleteExam': 'حذف امتحان',
    'getAllExams': 'عرض كافة الامتحانات',
    'updateExam': 'تعديل الامتحان',
    'myExams': 'امتحاناتي',
    'getAllAbsences': 'عرض كافة الغيابات',
    'createAbsence': 'إنشاء غياب',
    'getAbsenceById': 'عرض تفاصيل الغياب',
    'updateAbsence': 'تعديل الغياب',
    'deleteAbsence': 'حذف غياب',
}

api_path = '/home/hussam-eldeen/backup-5-May-26/graduation/backend/QIMS/routes/api.php'

with open(api_path, 'r', encoding='utf-8') as f:
    content = f.read()

def replace_perm(match):
    perm = match.group(1)
    if perm in translations:
        return "permission:" + translations[perm]
    print(f"Warning: permission '{perm}' not found in translations dictionary!")
    return "permission:" + perm

# Find middleware('permission:...') or middleware("permission:...")
new_content = re.sub(r"permission:([a-zA-Z0-9_\u0600-\u06FF]+)", replace_perm, content)

with open(api_path, 'w', encoding='utf-8') as f:
    f.write(new_content)

print("Successfully replaced permission strings in api.php!")
