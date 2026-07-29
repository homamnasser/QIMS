import {
  existsSync,
  mkdirSync,
  readFileSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = dirname(dirname(fileURLToPath(import.meta.url)));
const outputRoot = join(projectRoot, "api", "Mobile Staff API");
const collectionName = "QIMS Mobile Staff API";

const folderSequence = new Map([
  ["Authentication", 1],
  ["Reference Data", 2],
  ["Attendance", 3],
  ["Warnings", 4],
  ["Notes", 5],
  ["Sabrs", 6],
  ["Memorizations", 7],
  ["Exams", 8],
  ["Evaluations", 9],
  ["Teacher Compatibility", 10],
]);

const environmentVariables = [
  ["baseUrl", "http://localhost:8000"],
  ["staffEmail", "field.supervisor@example.com"],
  ["teacherEmail", "samer.khalil@example.com"],
  ["staffPassword", "password123"],
  ["deviceName", "Bruno Mobile Staff Device"],
  ["staffToken", ""],
  ["staffRefreshToken", ""],
  ["courseId", "1"],
  ["courseDateId", "1"],
  ["subjectId", "1"],
  ["lessonId", "1"],
  ["circleId", "1"],
  ["studentId", "1"],
  ["attendanceId", "1"],
  ["warningId", "1"],
  ["noteId", "1"],
  ["sabrId", "1"],
  ["memorizationId", "1"],
  ["examId", "1"],
  ["evaluationCycleId", "1"],
  ["evaluationCandidateId", "1"],
  ["evaluationPeriodId", "1"],
  ["evaluationRunId", "1"],
  ["evaluationResultId", "1"],
  ["certificateId", "1"],
  ["recognitionBatchId", "1"],
  ["administrationObservationId", "1"],
];

const requestBodies = new Map([
  [
    "POST api/mobile/staff/attendance",
    {
      student: "{{studentId}}",
      course: "{{courseId}}",
      date: "2026-08-01",
      type: "present",
      note: "تسجيل حضور من تطبيق الموبايل",
    },
  ],
  [
    "PUT api/mobile/staff/attendance/{id}",
    {
      date: "2026-08-01",
      type: "full",
      note: "تحديث سجل الحضور من تطبيق الموبايل",
    },
  ],
  [
    "POST api/mobile/staff/warnings",
    {
      student: "{{studentId}}",
      title: "إنذار تجريبي",
      description: "مثال محلي من Bruno",
      deduction_points: 1,
    },
  ],
  [
    "POST api/mobile/staff/notes",
    {
      student_id: "{{studentId}}",
      title: "ملاحظة تجريبية",
      description: "مثال محلي من Bruno",
    },
  ],
  [
    "POST api/mobile/staff/sabrs",
    {
      student: "{{studentId}}",
      course: "{{courseId}}",
      date: "2030-01-15",
      type: "داخلي",
      parts: [1],
    },
  ],
  [
    "PUT api/mobile/staff/sabrs/{id}",
    {
      value: "ممتاز",
      note: "تم تحديث نتيجة السبر من تطبيق الموبايل",
    },
  ],
  [
    "POST api/mobile/staff/memorizations",
    {
      student_id: "{{studentId}}",
      circle_id: "{{circleId}}",
      course_id: "{{courseId}}",
      record_type: "memorization",
      recorded_at: "2026-08-01",
      name: "تسميع تجريبي",
      start_page: 1,
      end_page: 2,
    },
  ],
  [
    "POST api/mobile/staff/exams",
    {
      student: "{{studentId}}",
      subject: "{{subjectId}}",
      course: "{{courseId}}",
      mark: 90,
    },
  ],
  ["PUT api/mobile/staff/exams/{id}", { mark: 95 }],
  [
    "POST api/mobile/staff/evaluation-cycles",
    {
      project_id: 1,
      name: "دورة تقييم تجريبية",
      season: "summer",
      top_students_count: 10,
      course_ids: ["{{courseId}}"],
      periods: [
        {
          name: "الفترة الأولى",
          sequence: 1,
          start_date: "2026-07-01",
          end_date: "2026-07-31",
        },
      ],
    },
  ],
  [
    "PUT api/mobile/staff/evaluation-cycles/{cycle}/status",
    { status: "data_collection" },
  ],
  [
    "POST api/mobile/staff/evaluation-cycles/{cycle}/runs",
    { preview: true },
  ],
  [
    "PUT api/mobile/staff/evaluation-candidates/{candidate}/teacher-evaluation",
    {
      evaluation_period_id: "{{evaluationPeriodId}}",
      circle_id: "{{circleId}}",
      behavior_score: 10,
      participation_score: 10,
      teacher_opinion_score: 10,
      comments: "تقييم تجريبي من تطبيق الموبايل",
      status: "submitted",
    },
  ],
  [
    "PUT api/mobile/staff/evaluation-candidates/{candidate}/quran-assessment",
    {
      evaluation_period_id: "{{evaluationPeriodId}}",
      circle_id: "{{circleId}}",
      below_minimum: false,
      notes: "تحقق تجريبي من الحد الأدنى",
    },
  ],
  [
    "POST api/mobile/staff/certificates/{certificate}/revoke",
    { reason: "إلغاء تجريبي للشهادة من Bruno" },
  ],
  [
    "PUT api/mobile/teacher/evaluation-candidates/{candidate}/teacher-evaluation",
    {
      evaluation_period_id: "{{evaluationPeriodId}}",
      circle_id: "{{circleId}}",
      behavior_score: 10,
      participation_score: 10,
      teacher_opinion_score: 10,
      comments: "اختبار مسار التوافق القديم",
      status: "submitted",
    },
  ],
  [
    "PUT api/mobile/teacher/evaluation-candidates/{candidate}/quran-assessment",
    {
      evaluation_period_id: "{{evaluationPeriodId}}",
      circle_id: "{{circleId}}",
      below_minimum: false,
      notes: "اختبار مسار التوافق القديم",
    },
  ],
]);

const parameterVariables = {
  id: "resourceId",
  courseId: "courseId",
  circleId: "circleId",
  studentId: "studentId",
  noteId: "noteId",
  cycle: "evaluationCycleId",
  candidate: "evaluationCandidateId",
  run: "evaluationRunId",
  result: "evaluationResultId",
  certificate: "certificateId",
  batch: "recognitionBatchId",
  observation: "administrationObservationId",
};

const routeSpecificParameters = new Map([
  ["api/mobile/staff/attendance/{id}", { id: "attendanceId" }],
  ["api/mobile/staff/warnings/{id}", { id: "warningId" }],
  ["api/mobile/staff/sabrs/{id}", { id: "sabrId" }],
  ["api/mobile/staff/memorizations/{id}", { id: "memorizationId" }],
  ["api/mobile/staff/exams/{id}", { id: "examId" }],
  ["api/mobile/staff/courses/{id}", { id: "courseId" }],
  ["api/mobile/staff/subjects/{id}", { id: "subjectId" }],
  ["api/mobile/staff/lessons/{id}", { id: "lessonId" }],
  ["api/mobile/staff/circles/{id}", { id: "circleId" }],
  ["api/mobile/staff/students/{id}", { id: "studentId" }],
  ["api/mobile/staff/course-dates/{id}/lessons", { id: "courseDateId" }],
]);

const routes = loadRoutes();
const staffRoutes = routes
  .filter(
    (route) =>
      route.uri.startsWith("api/mobile/auth/") ||
      route.uri.startsWith("api/mobile/staff/") ||
      route.uri.startsWith("api/mobile/teacher/"),
  )
  .sort(compareRoutes);

prepareOutput();
writeCollection();
writeEnvironment();
writeReadme(staffRoutes);

const sequences = new Map();
for (const route of staffRoutes) {
  const folder = folderFor(route);
  ensureFolder(folder);
  const sequence = (sequences.get(folder) ?? 0) + 1;
  sequences.set(folder, sequence);
  const fileName = `${String(sequence).padStart(2, "0")} ${safeName(requestName(route))}.yml`;
  writeFile(join(outputRoot, folder, fileName), renderRequest(route, sequence));
}

writeCoverage(staffRoutes);

console.log(
  `Generated ${staffRoutes.length} mobile staff requests in ${relative(projectRoot, outputRoot)}`,
);

function loadRoutes() {
  const raw = readFileSync(0, "utf8");
  if (!raw.trim()) {
    throw new Error(
      "Route JSON is required on stdin. Run: php artisan route:list --json | node scripts/generate_mobile_staff_bruno_collection.mjs",
    );
  }

  return JSON.parse(raw).map((route) => ({
    method: route.method.replace("|HEAD", ""),
    uri: route.uri,
    action: route.action.replace("App\\Http\\Controllers\\", ""),
    middleware: route.middleware,
  }));
}

function prepareOutput() {
  if (existsSync(outputRoot)) {
    const rootFile = join(outputRoot, "opencollection.yml");
    if (
      !existsSync(rootFile) ||
      !readFileSync(rootFile, "utf8").includes(`name: ${collectionName}`)
    ) {
      throw new Error(`Refusing to replace an unrecognized directory: ${outputRoot}`);
    }
    rmSync(outputRoot, { recursive: true });
  }
  mkdirSync(outputRoot, { recursive: true });
}

function writeCollection() {
  writeFile(
    join(outputRoot, "opencollection.yml"),
    `opencollection: 1.0.0

info:
  name: ${collectionName}
bundled: false
extensions:
  bruno:
    ignore:
      - node_modules
      - .git
      - README_AR.md
      - ROUTE_COVERAGE.md
`,
  );
}

function writeEnvironment() {
  const variables = environmentVariables
    .map(
      ([name, value]) => `  - name: ${name}
    value: ${yamlScalar(value)}`,
    )
    .join("\n");

  writeFile(
    join(outputRoot, "environments", "Local.yml"),
    `name: Local
variables:
${variables}
`,
  );
}

function writeReadme(collectionRoutes) {
  const staffCount = collectionRoutes.filter((route) =>
    route.uri.startsWith("api/mobile/staff/"),
  ).length;
  const compatibilityCount = collectionRoutes.filter((route) =>
    route.uri.startsWith("api/mobile/teacher/"),
  ).length;

  writeFile(
    join(outputRoot, "README_AR.md"),
    `# مجموعة Bruno لكادر تطبيق الموبايل

تغطي هذه المجموعة ${collectionRoutes.length} طلبًا: أربعة طلبات للمصادقة،
و${staffCount} مسارًا موحدًا تحت \`/api/mobile/staff\`، و${compatibilityCount}
مسارات توافق للمعلم تحت \`/api/mobile/teacher\`.

## طريقة التشغيل

1. اختر بيئة \`Local\`.
2. شغّل \`Authentication/01 POST Login\`. الحساب الافتراضي هو المشرف
   الميداني المحلي \`field.supervisor@example.com\` وكلمة مروره
   \`password123\`.
3. يتحقق طلب \`Authentication/02 GET Me\` من نوع الحساب وعائلة الدور.
4. شغّل طلبات القراءة أولًا، ثم راجع المعرّفات والأجسام قبل طلبات التعديل.
5. استخدم \`Authentication/03 POST Refresh\` لتدوير الرمزين، ثم
   \`Authentication/04 POST Logout\` لإبطالهما ومسحهما من البيئة.

لاختبار المعلم غيّر \`staffEmail\` إلى قيمة \`teacherEmail\`. سيعمل فقط ما
تسمح به صلاحيات دور المعلم؛ ظهور 403 في عملية غير ممنوحة له نتيجة صحيحة.

## حدود الأمان

- لا تستخدم المجموعة Cookies جلسة الويب أو CSRF؛ تطبيق الموبايل يستخدم
  Access Token قصير العمر وRefresh Token دوّارًا ومستقلًا.
- تُخزن الرموز هنا في بيئة Bruno المحلية للاختبار فقط. يجب أن يخزنها
  التطبيق الحقيقي في مخزن مفاتيح نظام التشغيل وألا يطبعها في السجلات.
- كل مسار تشغيلي يمر أولًا عبر قناة \`mobile-staff\`، ثم عبر صلاحية Spatie
  الخاصة بالعملية. امتلاك الرمز وحده لا يمنح العملية.
- طلبات POST وPUT وDELETE قد تغيّر البيانات. استخدم قاعدة محلية واختبر
  المعرّفات قبل الإرسال.
`,
  );
}

function ensureFolder(folder) {
  const directory = join(outputRoot, folder);
  if (existsSync(directory)) return;

  mkdirSync(directory, { recursive: true });
  writeFile(
    join(directory, "folder.yml"),
    `info:
  name: ${folder}
  type: folder
  seq: ${folderSequence.get(folder)}
`,
  );
}

function renderRequest(route, sequence) {
  const key = `${route.method} ${route.uri}`;
  const parameters = extractParameters(route.uri);
  const url = route.uri.replace(
    /\{([^}]+)\}/g,
    (_match, parameter) => `:${parameter}`,
  );
  const body = requestBody(route);
  const auth = requestAuthentication(route);
  const params = parameters.length
    ? `  params:
${parameters
  .map(
    ({ name, variable }) => `    - name: ${name}
      value: "{{${variable}}}"
      type: path`,
  )
  .join("\n")}
`
    : "";

  return `info:
  name: ${requestName(route)}
  type: http
  seq: ${sequence}

http:
  method: ${route.method}
  url: "{{baseUrl}}/${url}"
${params}  headers:
    - name: Accept
      value: application/json
${body ? "    - name: Content-Type\n      value: application/json\n" : ""}${auth}${body}
runtime:
  scripts:
${requestScripts(route)}

settings:
  encodeUrl: true
  timeout: 0
  followRedirects: true
  maxRedirects: 5

docs: |-
${indent(documentation(route, key))}
`;
}

function requestAuthentication(route) {
  if (route.uri === "api/mobile/auth/login") return "";

  const token =
    route.uri === "api/mobile/auth/refresh"
      ? "staffRefreshToken"
      : "staffToken";

  return `  auth:
    type: bearer
    token: "{{${token}}}"
`;
}

function requestBody(route) {
  const key = `${route.method} ${route.uri}`;
  let body = requestBodies.get(key);

  if (key === "POST api/mobile/auth/login") {
    body = {
      email: "{{staffEmail}}",
      password: "{{staffPassword}}",
      device_name: "{{deviceName}}",
    };
  } else if (key === "POST api/mobile/auth/refresh") {
    return "";
  }

  if (!body && ["POST", "PUT", "PATCH"].includes(route.method)) {
    body = {};
  }
  if (!body) return "";

  return `  body:
    type: json
    data: |-
${indent(JSON.stringify(body, null, 2), 6)}
`;
}

function requestScripts(route) {
  const key = `${route.method} ${route.uri}`;

  if (key === "POST api/mobile/auth/login") {
    return `    - type: after-response
      code: |-
        if (res.status === 200 && res.body?.data?.access_token) {
          bru.setEnvVar("staffToken", String(res.body.data.access_token));
          bru.setEnvVar("staffRefreshToken", String(res.body.data.refresh_token));
        }
    - type: tests
      code: |-
        test("logs in an allowed mobile staff account", function() {
          expect(res.status).to.equal(200);
          expect(res.body.data.user.account_type).to.equal("staff");
          expect(["teacher", "field-supervisor"]).to.include(
            res.body.data.user.role_family
          );
          expect(res.body.data.access_token).to.be.a("string");
          expect(res.body.data.refresh_token).to.be.a("string");
        });`;
  }

  if (key === "POST api/mobile/auth/refresh") {
    return `    - type: after-response
      code: |-
        if (res.status === 200 && res.body?.data?.access_token) {
          bru.setEnvVar("staffToken", String(res.body.data.access_token));
          bru.setEnvVar("staffRefreshToken", String(res.body.data.refresh_token));
        }
    - type: tests
      code: |-
        test("rotates the mobile staff token pair", function() {
          expect(res.status).to.equal(200);
          expect(res.body.data.access_token).to.be.a("string");
          expect(res.body.data.refresh_token).to.be.a("string");
        });`;
  }

  if (key === "POST api/mobile/auth/logout") {
    return `    - type: after-response
      code: |-
        if (res.status === 200) {
          bru.setEnvVar("staffToken", "");
          bru.setEnvVar("staffRefreshToken", "");
        }
    - type: tests
      code: |-
        test("revokes the current mobile device token pair", function() {
          expect(res.status).to.equal(200);
        });`;
  }

  if (key === "GET api/mobile/auth/me") {
    return `    - type: tests
      code: |-
        test("returns the authenticated mobile staff account", function() {
          expect(res.status).to.equal(200);
          expect(res.body.data.user.account_type).to.equal("staff");
        });`;
  }

  return `    - type: tests
      code: |-
        test("passes mobile staff authentication and authorization", function() {
          expect(res.status).to.not.equal(401);
          expect(res.status).to.not.equal(403);
        });`;
}

function documentation(route, key) {
  const permission = route.middleware
    .find((middleware) => middleware.includes("PermissionMiddleware:"))
    ?.split("PermissionMiddleware:")[1];
  const compatibility = route.uri.startsWith("api/mobile/teacher/")
    ? "\n\nهذا مسار توافق قديم؛ المسار الجديد المكافئ موجود تحت `/api/mobile/staff`."
    : "";
  const mutation = ["POST", "PUT", "PATCH", "DELETE"].includes(route.method)
    ? "\n\nتنبيه: قد يغيّر هذا الطلب البيانات المحلية."
    : "";

  return `يختبر \`${key}\` باستخدام Bearer Access Token خاص بقناة كادر الموبايل.${
    permission ? `\n\nالصلاحية المطلوبة: \`${permission}\`.` : ""
  }${compatibility}${mutation}`;
}

function extractParameters(uri) {
  const overrides = routeSpecificParameters.get(uri) ?? {};
  return [...uri.matchAll(/\{([^}]+)\}/g)].map((match) => ({
    name: match[1],
    variable:
      overrides[match[1]] ?? parameterVariables[match[1]] ?? match[1],
  }));
}

function folderFor(route) {
  const uri = route.uri;
  if (uri.startsWith("api/mobile/auth/")) return "Authentication";
  if (uri.startsWith("api/mobile/teacher/")) return "Teacher Compatibility";
  if (uri.includes("/attendance")) return "Attendance";
  if (uri.includes("/warnings")) return "Warnings";
  if (uri.includes("/notes")) return "Notes";
  if (uri.includes("/sabrs")) return "Sabrs";
  if (uri.includes("/memorizations")) return "Memorizations";
  if (uri.includes("/exams")) return "Exams";
  if (
    uri.includes("/evaluation-") ||
    uri.includes("/certificates") ||
    uri.includes("/recognition-") ||
    uri.includes("/administration-observations")
  ) {
    return "Evaluations";
  }
  return "Reference Data";
}

function compareRoutes(left, right) {
  const folderDifference =
    folderSequence.get(folderFor(left)) - folderSequence.get(folderFor(right));
  if (folderDifference) return folderDifference;

  const authOrder = new Map([
    ["POST api/mobile/auth/login", 1],
    ["GET api/mobile/auth/me", 2],
    ["POST api/mobile/auth/refresh", 3],
    ["POST api/mobile/auth/logout", 4],
  ]);
  const leftKey = `${left.method} ${left.uri}`;
  const rightKey = `${right.method} ${right.uri}`;
  if (authOrder.has(leftKey) || authOrder.has(rightKey)) {
    return (
      (authOrder.get(leftKey) ?? Number.MAX_SAFE_INTEGER) -
      (authOrder.get(rightKey) ?? Number.MAX_SAFE_INTEGER)
    );
  }

  return left.uri.localeCompare(right.uri) || left.method.localeCompare(right.method);
}

function requestName(route) {
  const action = route.action.split("@")[1] ?? route.uri.split("/").at(-1);
  const readable = action
    .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
    .replace(/^./, (character) => character.toUpperCase());
  return `${route.method} ${readable}`;
}

function writeCoverage(collectionRoutes) {
  const rows = collectionRoutes
    .map(
      (route) =>
        `| ${route.method} | \`/${route.uri}\` | ${folderFor(route)} |`,
    )
    .join("\n");

  writeFile(
    join(outputRoot, "ROUTE_COVERAGE.md"),
    `# Mobile Staff Route Coverage

Generated from \`php artisan route:list --json\`.

| Method | Route | Folder |
|---|---|---|
${rows}
`,
  );
}

function writeFile(path, content) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, content);
}

function indent(value, spaces = 2) {
  const prefix = " ".repeat(spaces);
  return value
    .split("\n")
    .map((line) => `${prefix}${line}`)
    .join("\n");
}

function yamlScalar(value) {
  return `"${String(value).replaceAll("\\", "\\\\").replaceAll('"', '\\"')}"`;
}

function safeName(value) {
  return value.replace(/[<>:"/\\|?*\u0000-\u001f]/g, "-");
}
