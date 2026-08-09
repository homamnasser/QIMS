# Mobile Staff Route Coverage

Generated from `php artisan route:list --json`.

| Method | Route | Folder |
|---|---|---|
| POST | `/api/mobile/auth/login` | Authentication |
| GET | `/api/mobile/auth/me` | Authentication |
| POST | `/api/mobile/auth/refresh` | Authentication |
| POST | `/api/mobile/auth/logout` | Authentication |
| GET | `/api/mobile/staff/circles` | Reference Data |
| GET | `/api/mobile/staff/circles/{circleId}/students` | Reference Data |
| GET | `/api/mobile/staff/circles/{id}` | Reference Data |
| GET | `/api/mobile/staff/circles/mine/curriculum` | Reference Data |
| GET | `/api/mobile/staff/course-dates/{id}/lessons` | Reference Data |
| GET | `/api/mobile/staff/courses` | Reference Data |
| GET | `/api/mobile/staff/courses/{courseId}/curriculum` | Reference Data |
| GET | `/api/mobile/staff/courses/{courseId}/dates` | Reference Data |
| GET | `/api/mobile/staff/courses/{id}` | Reference Data |
| GET | `/api/mobile/staff/lessons` | Reference Data |
| GET | `/api/mobile/staff/lessons/{id}` | Reference Data |
| GET | `/api/mobile/staff/students` | Reference Data |
| GET | `/api/mobile/staff/students/{id}` | Reference Data |
| GET | `/api/mobile/staff/subjects` | Reference Data |
| GET | `/api/mobile/staff/subjects/{id}` | Reference Data |
| GET | `/api/mobile/staff/attendance` | Attendance |
| POST | `/api/mobile/staff/attendance` | Attendance |
| DELETE | `/api/mobile/staff/attendance/{id}` | Attendance |
| GET | `/api/mobile/staff/attendance/{id}` | Attendance |
| PUT | `/api/mobile/staff/attendance/{id}` | Attendance |
| GET | `/api/mobile/staff/warnings` | Warnings |
| POST | `/api/mobile/staff/warnings` | Warnings |
| DELETE | `/api/mobile/staff/warnings/{id}` | Warnings |
| GET | `/api/mobile/staff/warnings/{id}` | Warnings |
| GET | `/api/mobile/staff/warnings/mine` | Warnings |
| POST | `/api/mobile/staff/notes` | Notes |
| DELETE | `/api/mobile/staff/notes/{noteId}` | Notes |
| GET | `/api/mobile/staff/notes/mine` | Notes |
| GET | `/api/mobile/staff/notes/students/{studentId}` | Notes |
| GET | `/api/mobile/staff/sabrs` | Sabrs |
| POST | `/api/mobile/staff/sabrs` | Sabrs |
| DELETE | `/api/mobile/staff/sabrs/{id}` | Sabrs |
| GET | `/api/mobile/staff/sabrs/{id}` | Sabrs |
| PUT | `/api/mobile/staff/sabrs/{id}` | Sabrs |
| GET | `/api/mobile/staff/sabrs/mine` | Sabrs |
| GET | `/api/mobile/staff/memorizations` | Memorizations |
| POST | `/api/mobile/staff/memorizations` | Memorizations |
| DELETE | `/api/mobile/staff/memorizations/{id}` | Memorizations |
| GET | `/api/mobile/staff/memorizations/{id}` | Memorizations |
| GET | `/api/mobile/staff/memorizations/mine` | Memorizations |
| GET | `/api/mobile/staff/reading-improvements` | Reading Improvements |
| POST | `/api/mobile/staff/reading-improvements` | Reading Improvements |
| DELETE | `/api/mobile/staff/reading-improvements/{readingImprovement}` | Reading Improvements |
| GET | `/api/mobile/staff/reading-improvements/{id}` | Reading Improvements |
| PUT | `/api/mobile/staff/reading-improvements/{readingImprovement}` | Reading Improvements |
| GET | `/api/mobile/staff/exams` | Exams |
| POST | `/api/mobile/staff/exams` | Exams |
| DELETE | `/api/mobile/staff/exams/{id}` | Exams |
| GET | `/api/mobile/staff/exams/{id}` | Exams |
| PUT | `/api/mobile/staff/exams/{id}` | Exams |
| GET | `/api/mobile/staff/exams/mine` | Exams |
| POST | `/api/mobile/staff/administration-observations/{observation}/approve` | Evaluations |
| GET | `/api/mobile/staff/certificates/{certificate}/download` | Evaluations |
| POST | `/api/mobile/staff/certificates/{certificate}/revoke` | Evaluations |
| GET | `/api/mobile/staff/evaluation-candidates` | Evaluations |
| PUT | `/api/mobile/staff/evaluation-candidates/{candidate}/quran-assessment` | Evaluations |
| GET | `/api/mobile/staff/evaluation-candidates/{candidate}/review` | Evaluations |
| PUT | `/api/mobile/staff/evaluation-candidates/{candidate}/teacher-evaluation` | Evaluations |
| GET | `/api/mobile/staff/evaluation-cycles` | Evaluations |
| POST | `/api/mobile/staff/evaluation-cycles` | Evaluations |
| GET | `/api/mobile/staff/evaluation-cycles/{cycle}` | Evaluations |
| GET | `/api/mobile/staff/evaluation-cycles/{cycle}/audit-events` | Evaluations |
| GET | `/api/mobile/staff/evaluation-cycles/{cycle}/readiness` | Evaluations |
| GET | `/api/mobile/staff/evaluation-cycles/{cycle}/recognition` | Evaluations |
| POST | `/api/mobile/staff/evaluation-cycles/{cycle}/runs` | Evaluations |
| PUT | `/api/mobile/staff/evaluation-cycles/{cycle}/status` | Evaluations |
| POST | `/api/mobile/staff/evaluation-cycles/{cycle}/sync-candidates` | Evaluations |
| POST | `/api/mobile/staff/evaluation-results/{result}/certificate` | Evaluations |
| GET | `/api/mobile/staff/evaluation-runs/{run}` | Evaluations |
| POST | `/api/mobile/staff/evaluation-runs/{run}/approve` | Evaluations |
| POST | `/api/mobile/staff/evaluation-runs/{run}/certificates` | Evaluations |
| POST | `/api/mobile/staff/evaluation-runs/{run}/publish` | Evaluations |
| POST | `/api/mobile/staff/recognition-batches/{batch}/approve` | Evaluations |
| POST | `/api/mobile/staff/recognition-batches/{batch}/publish` | Evaluations |
| GET | `/api/mobile/teacher/evaluation-candidates` | Teacher Compatibility |
| PUT | `/api/mobile/teacher/evaluation-candidates/{candidate}/quran-assessment` | Teacher Compatibility |
| GET | `/api/mobile/teacher/evaluation-candidates/{candidate}/review` | Teacher Compatibility |
| PUT | `/api/mobile/teacher/evaluation-candidates/{candidate}/teacher-evaluation` | Teacher Compatibility |
