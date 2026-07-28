import {
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { basename, dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = dirname(dirname(fileURLToPath(import.meta.url)));
const legacyRoot = join(projectRoot, "api", "QIMS");
const outputRoot = join(projectRoot, "api", "Web User API");
const collectionName = "QIMS Web User API";

const folderConfiguration = [
  ["Authentication", 1],
  ["Staff", 2],
  ["Roles", 3],
  ["Projects", 4],
  ["Mosques", 5],
  ["Courses", 6],
  ["Subjects", 7],
  ["Lessons", 8],
  ["Course Dates", 9],
  ["Curriculum", 10],
  ["Circles", 11],
  ["Students", 12],
  ["Enrollments", 13],
  ["Notes", 14],
  ["Sabrs", 15],
  ["Memorizations", 16],
  ["Warnings", 17],
  ["Exams", 18],
  ["Absences", 19],
  ["Surveys", 20],
  ["Public Surveys", 21],
  ["Reports", 22],
];

const folderSequence = new Map(folderConfiguration);

const environmentVariables = [
  ["baseUrl", "http://localhost:8000"],
  ["webOrigin", "http://localhost:5173"],
  ["webEmail", "superadmin@gmail.com"],
  ["webPassword", "password123"],
  ["xsrfToken", ""],
  ["staffId", "2"],
  ["roleId", "2"],
  ["projectId", "1"],
  ["mosqueId", "1"],
  ["courseId", "1"],
  ["subjectId", "1"],
  ["lessonId", "1"],
  ["courseDateId", "1"],
  ["circleId", "1"],
  ["studentId", "1"],
  ["noteId", "1"],
  ["sabrId", "1"],
  ["memorizationId", "1"],
  ["warningId", "1"],
  ["examId", "1"],
  ["absenceId", "1"],
  ["surveyId", "1"],
  ["surveyResponseId", "1"],
  ["surveyFileAccessToken", "replace-with-survey-file-access-token"],
  ["publicSurveyToken", "replace-with-public-survey-token"],
  ["surveyParticipantAccessToken", "replace-after-identify-request"],
  ["studentSelfnumber", "TEST01-000001"],
];

const specialBodies = new Map([
  [
    "POST api/surveys",
    jsonBody({
      name: "استبيان تجريبي من Bruno",
      description: "استبيان محلي لاختبار واجهات النظام.",
      starts_at: null,
      ends_at: null,
      allow_multiple_responses: false,
    }),
  ],
  [
    "PUT api/surveys/{survey}",
    jsonBody({
      name: "استبيان تجريبي محدث",
      description: "تحديث محلي من مجموعة Bruno.",
      starts_at: null,
      ends_at: null,
      allow_multiple_responses: false,
    }),
  ],
  [
    "PUT api/surveys/{survey}/definition",
    jsonBody({
      sections: [
        {
          client_id: "section-main",
          title: "القسم الرئيسي",
          description: null,
          questions: [
            {
              client_id: "question-main",
              type: "radio",
              title: "هل ترغب بالمتابعة؟",
              description: null,
              is_required: true,
              validation_rules: [],
              settings: {},
              options: [
                { label: "نعم", value: "yes" },
                { label: "لا", value: "no" },
              ],
            },
          ],
        },
      ],
      student_fields: [],
      logic_rules: [],
    }),
  ],
  [
    "POST api/surveys/{survey}/publication",
    jsonBody({ publish: true }),
  ],
  [
    "POST api/public/surveys/{publicToken}/identify",
    jsonBody({ selfnumber: "{{studentSelfnumber}}" }),
  ],
  [
    "POST api/public/surveys/{publicToken}/responses",
    jsonBody({
      access_token: "{{surveyParticipantAccessToken}}",
      answers: { "1": "yes" },
      student_fields: {},
    }),
  ],
]);

const requestNameOverrides = new Map([
  ["GET sanctum/csrf-cookie", "Initialize CSRF Cookies"],
  ["POST api/web/auth/login", "Login as Web User"],
  ["GET api/web/auth/me", "Get Current Web User"],
  ["POST api/web/auth/logout", "Logout Web User"],
  ["GET api/project/getAudiences", "Get Project Audiences"],
  ["GET api/courses-students", "Get Courses and Students Report"],
  ["GET api/courses-dates-lessons", "Get Course Dates and Lessons Report"],
  ["GET api/student-info", "Get Student Information Report"],
]);

const legacyTemplates = loadLegacyTemplates();
const routes = loadLaravelRoutes();
const webRoutes = routes.filter(isWebCollectionRoute).sort(compareRoutes);

prepareOutputDirectory();
writeCollectionRoot();
writeEnvironment();
writeReadme(webRoutes);

const sequenceByFolder = new Map();
for (const route of webRoutes) {
  const folder = folderFor(route);
  ensureFolder(folder);

  const sequence = (sequenceByFolder.get(folder) ?? 0) + 1;
  sequenceByFolder.set(folder, sequence);

  const content = renderRequest(route, folder, sequence);
  const fileName = `${String(sequence).padStart(2, "0")} ${safeFileName(
    requestName(route),
  )}.yml`;

  writeFile(join(outputRoot, folder, fileName), content);
}

writeCoverage(webRoutes);

console.log(
  `Generated ${webRoutes.length} web/public requests in ${relative(
    projectRoot,
    outputRoot,
  )}`,
);

function loadLaravelRoutes() {
  const raw = readFileSync(0, "utf8");

  if (!raw.trim()) {
    throw new Error(
      "Route JSON is required on stdin. Run: php artisan route:list --json | node scripts/generate_web_user_bruno_collection.mjs",
    );
  }

  return JSON.parse(raw)
    .filter(
      (route) =>
        route.uri.startsWith("api/") || route.uri === "sanctum/csrf-cookie",
    )
    .map((route) => ({
      method: route.method.replace("|HEAD", ""),
      uri: route.uri,
      action: route.action.replace("App\\Http\\Controllers\\", ""),
      middleware: route.middleware,
    }));
}

function isWebCollectionRoute(route) {
  if (route.uri.startsWith("api/mobile/")) {
    return false;
  }

  if (route.uri === "sanctum/csrf-cookie") {
    return true;
  }

  if (route.uri.startsWith("api/web/auth/")) {
    return true;
  }

  if (route.uri.startsWith("api/public/surveys/")) {
    return true;
  }

  return route.middleware.some((middleware) =>
    middleware.includes("EnsureAuthenticationChannel:web"),
  );
}

function compareRoutes(left, right) {
  const leftFolderSequence = folderSequence.get(folderFor(left)) ?? 99;
  const rightFolderSequence = folderSequence.get(folderFor(right)) ?? 99;

  if (leftFolderSequence !== rightFolderSequence) {
    return leftFolderSequence - rightFolderSequence;
  }

  const authenticationOrder = new Map([
    ["GET sanctum/csrf-cookie", 1],
    ["POST api/web/auth/login", 2],
    ["GET api/web/auth/me", 3],
    ["POST api/web/auth/logout", 4],
  ]);
  const leftKey = `${left.method} ${left.uri}`;
  const rightKey = `${right.method} ${right.uri}`;
  const leftAuthenticationOrder = authenticationOrder.get(leftKey);
  const rightAuthenticationOrder = authenticationOrder.get(rightKey);

  if (
    leftAuthenticationOrder !== undefined ||
    rightAuthenticationOrder !== undefined
  ) {
    return (
      (leftAuthenticationOrder ?? Number.MAX_SAFE_INTEGER) -
      (rightAuthenticationOrder ?? Number.MAX_SAFE_INTEGER)
    );
  }

  return (
    left.uri.localeCompare(right.uri) || left.method.localeCompare(right.method)
  );
}

function loadLegacyTemplates() {
  const templates = [];

  for (const path of walkFiles(legacyRoot)) {
    if (!path.endsWith(".yml") || basename(path) === "folder.yml") {
      continue;
    }

    const content = readFileSync(path, "utf8");
    const method = content.match(/^  method:\s*(\S+)/m)?.[1];
    const rawUrl = content.match(/^  url:\s*(.+)$/m)?.[1]?.trim();

    if (!method || !rawUrl) {
      continue;
    }

    const url = rawUrl.replace(/^["']|["']$/g, "");
    const pathAndQuery = url.replace(/^https?:\/\/[^/]+\/?/, "");
    const routePath = pathAndQuery.split("?")[0].replace(/\/+$/, "");

    templates.push({
      method,
      normalizedPath: normalizeTemplatePath(routePath),
      body: sanitizeLegacyBody(extractHttpSection(content, "body")),
      params: extractHttpSection(content, "params"),
    });
  }

  return templates;
}

function sanitizeLegacyBody(body) {
  const sanitized = body.replace(
    /^(\s+)type: file\n\1value:\n(?:\1  - .*(?:\n|$))+(?:\1disabled: true(?:\n|$))?/gm,
    (_match, indentation) =>
      `${indentation}type: file\n${indentation}value: []\n${indentation}disabled: true\n`,
  );

  return /^\s+data:/m.test(sanitized) ? sanitized : "";
}

function walkFiles(directory) {
  const files = [];

  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...walkFiles(path));
    } else {
      files.push(path);
    }
  }

  return files;
}

function normalizeTemplatePath(path) {
  return path
    .replace(/^\/+/, "")
    .replace(/\/\d+(?=\/|$)/g, "/{}")
    .replace(/\/+$/, "");
}

function normalizeLaravelPath(path) {
  return path.replace(/\{[^}]+\}/g, "{}").replace(/\/+$/, "");
}

function extractHttpSection(content, sectionName) {
  const lines = content.split("\n");
  const start = lines.findIndex((line) => line === `  ${sectionName}:`);

  if (start < 0) {
    return "";
  }

  let end = lines.length;
  for (let index = start + 1; index < lines.length; index += 1) {
    if (/^  [a-zA-Z][a-zA-Z-]*:/.test(lines[index])) {
      end = index;
      break;
    }
    if (/^[a-zA-Z][a-zA-Z-]*:/.test(lines[index])) {
      end = index;
      break;
    }
  }

  return lines.slice(start, end).join("\n").trimEnd();
}

function prepareOutputDirectory() {
  if (existsSync(outputRoot)) {
    const rootFile = join(outputRoot, "opencollection.yml");
    const owned =
      existsSync(rootFile) &&
      readFileSync(rootFile, "utf8").includes(`name: ${collectionName}`);

    if (!owned) {
      throw new Error(
        `Refusing to replace an unrecognized directory: ${outputRoot}`,
      );
    }

    rmSync(outputRoot, { recursive: true });
  }

  mkdirSync(outputRoot, { recursive: true });
}

function writeCollectionRoot() {
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
  const values = environmentVariables
    .map(
      ([name, value]) => `  - name: ${name}
    value: ${yamlScalar(value)}`,
    )
    .join("\n");

  writeFile(
    join(outputRoot, "environments", "Local.yml"),
    `name: Local
variables:
${values}
`,
  );
}

function writeReadme(webRoutes) {
  const protectedCount = webRoutes.filter(isProtectedWebRoute).length;
  const unsafeCount = webRoutes.filter((route) =>
    isUnsafe(route.method),
  ).length;

  writeFile(
    join(outputRoot, "README_AR.md"),
    `# مجموعة Bruno لمستخدم لوحة الويب

تغطي هذه المجموعة ${webRoutes.length} طلبًا: ${protectedCount} مسارًا محميًا
بجلسة الويب، ومسارات المصادقة، ومسارات الاستبيان العامة. يوجد ${unsafeCount}
طلبًا يغيّر البيانات أو قد يحذفها.

## ترتيب التشغيل

1. اختر بيئة \`Local\`.
2. شغّل \`Authentication/01 Initialize CSRF Cookies\`.
3. شغّل \`Authentication/02 Login as Web User\`.
4. تحقق من الجلسة عبر \`Authentication/03 Get Current Web User\`.
5. شغّل طلبات القراءة أو التعديل المطلوبة.
6. أنهِ الجلسة عبر \`Authentication/04 Logout Web User\`.

تحتفظ Bruno تلقائيًا بـCookie الجلسة. طلب تهيئة CSRF يقرأ Cookie العامة
\`XSRF-TOKEN\` ويحفظ قيمتها في متغير البيئة \`xsrfToken\`، ثم ترسل طلبات
POST وPUT وPATCH وDELETE الترويسة \`X-XSRF-TOKEN\`.

## تنبيه

طلبات الإنشاء والتعديل والحذف ليست Smoke Tests آمنة على بيانات مهمة. القيم
الافتراضية مناسبة لبيانات \`TestDataSeeder\` المحلية، ويجب مراجعتها قبل
إرسال الطلب. لا تستخدم \`migrate:fresh --seed\` على قاعدة إنتاج.
`,
  );
}

function ensureFolder(folder) {
  const path = join(outputRoot, folder);
  if (existsSync(path)) {
    return;
  }

  mkdirSync(path, { recursive: true });
  writeFile(
    join(path, "folder.yml"),
    `info:
  name: ${folder}
  type: folder
  seq: ${folderSequence.get(folder) ?? 99}
`,
  );
}

function renderRequest(route, folder, sequence) {
  const name = requestName(route);
  const publicRoute = isPublicRoute(route);
  const pathParameters = extractPathParameters(route.uri);
  const queryParameters = requestQueryParameters(route);
  const renderedUrl = renderUrl(route.uri, pathParameters);
  const body = requestBody(route);
  const tags = requestTags(route);
  const headers = requestHeaders(route);
  const scripts = requestScripts(route);

  const parameterEntries = [
    ...pathParameters.map(
      ({ name: parameterName, value }) => `    - name: ${parameterName}
      value: "{{${value}}}"
      type: path`,
    ),
    ...queryParameters,
  ];
  const parametersYaml =
    parameterEntries.length === 0
      ? ""
      : `  params:
${parameterEntries.join("\n")}
`;

  const bodyYaml = body ? `${indentBody(body)}\n` : "";

  return `info:
  name: ${name}
  type: http
  seq: ${sequence}
  tags:
${tags.map((tag) => `    - ${tag}`).join("\n")}

http:
  method: ${route.method}
  url: "${renderedUrl}"
${parametersYaml}${headers}${bodyYaml}
runtime:
  scripts:
${scripts}

settings:
  encodeUrl: true
  timeout: 0
  followRedirects: true
  maxRedirects: 5

docs: |-
${indentDocumentation(documentation(route, folder, publicRoute))}
`;
}

function requestHeaders(route) {
  const headers = [
    ["Accept", "application/json"],
    ["Origin", "{{webOrigin}}"],
  ];

  if (isUnsafe(route.method) && route.uri !== "sanctum/csrf-cookie") {
    headers.push(["X-XSRF-TOKEN", "{{xsrfToken}}"]);
  }

  return `  headers:
${headers
  .map(
    ([name, value]) => `    - name: ${name}
      value: ${yamlScalar(value)}`,
  )
  .join("\n")}
`;
}

function requestBody(route) {
  const key = `${route.method} ${route.uri}`;

  if (key === "POST api/web/auth/login") {
    return jsonBody({
      email: "{{webEmail}}",
      password: "{{webPassword}}",
    });
  }

  if (specialBodies.has(key)) {
    return specialBodies.get(key);
  }

  return legacyTemplateFor(route)?.body ?? "";
}

function requestQueryParameters(route) {
  const reportParameters = {
    "GET api/courses-students": [
      `    - name: limit
      value: "9"
      type: query
      description: Number of courses per page`,
    ],
    "GET api/student-info": [
      `    - name: limit
      value: "9"
      type: query
      description: Number of students per page`,
    ],
  };
  const key = `${route.method} ${route.uri}`;
  if (reportParameters[key]) {
    return reportParameters[key];
  }

  const rawParameters = legacyTemplateFor(route)?.params ?? "";
  if (!rawParameters) {
    return [];
  }

  return [rawParameters.replace(/^  params:\n/, "")];
}

function legacyTemplateFor(route) {
  return legacyTemplates.find(
    (candidate) =>
      candidate.method === route.method &&
      candidate.normalizedPath === normalizeLaravelPath(route.uri),
  );
}

function indentBody(body) {
  return `${body}\n`;
}

function requestScripts(route) {
  const key = `${route.method} ${route.uri}`;

  if (key === "GET sanctum/csrf-cookie") {
    return `    - type: after-response
      code: |-
        const token = bru.cookies.get("XSRF-TOKEN");
        if (token) {
          bru.setEnvVar("xsrfToken", decodeURIComponent(token));
        }
    - type: tests
      code: |-
        test("initializes the CSRF cookie", function() {
          expect(res.status).to.equal(204);
          expect(bru.cookies.has("XSRF-TOKEN")).to.equal(true);
        });`;
  }

  if (key === "POST api/web/auth/login") {
    return `    - type: tests
      code: |-
        test("logs in with a server-side web session", function() {
          expect(res.status).to.equal(200);
          expect(res.body.data.user.account_type).to.equal("staff");
          expect(res.body.data).to.not.have.property("access_token");
          expect(res.body.data).to.not.have.property("token");
          expect(bru.cookies.has("qims_web_session")).to.equal(true);
        });`;
  }

  if (key === "GET api/web/auth/me") {
    return `    - type: tests
      code: |-
        test("returns the authenticated web user", function() {
          expect(res.status).to.equal(200);
          expect(res.body.data.user.account_type).to.equal("staff");
        });`;
  }

  if (key === "POST api/web/auth/logout") {
    return `    - type: after-response
      code: |-
        if (res.status === 200) {
          bru.setEnvVar("xsrfToken", "");
        }
    - type: tests
      code: |-
        test("invalidates the web session", function() {
          expect(res.status).to.equal(200);
        });`;
  }

  if (key === "POST api/public/surveys/{publicToken}/identify") {
    return `    - type: after-response
      code: |-
        if (res.status === 200 && res.body?.data?.access_token) {
          bru.setEnvVar(
            "surveyParticipantAccessToken",
            String(res.body.data.access_token)
          );
        }
    - type: tests
      code: |-
        test("returns a controlled public-survey response", function() {
          expect([200, 404, 422]).to.include(res.status);
        });`;
  }

  if (isPublicRoute(route)) {
    return `    - type: tests
      code: |-
        test("returns a controlled public API response", function() {
          expect([200, 201, 404, 409, 422]).to.include(res.status);
        });`;
  }

  return `    - type: tests
      code: |-
        test("passes web authentication, channel and CSRF checks", function() {
          expect(res.status).to.not.equal(401);
          expect(res.status).to.not.equal(403);
          expect(res.status).to.not.equal(419);
        });`;
}

function documentation(route, folder, publicRoute) {
  const permission = route.middleware
    .find((middleware) => middleware.includes("PermissionMiddleware:"))
    ?.split("PermissionMiddleware:")[1];
  const mutationWarning = isUnsafe(route.method)
    ? "\n\nتنبيه: هذا الطلب قد يغيّر البيانات. راجع القيم قبل الإرسال."
    : "";
  const permissionText = permission
    ? `\n\nالصلاحية المطلوبة: \`${permission}\`.`
    : "";
  const authText = publicRoute
    ? "المسار عام ولا يعتمد على جلسة الموظف، لكنه يبقى خاضعًا للتحقق من المدخلات وتحديد المعدل."
    : "المسار يستخدم جلسة الويب HttpOnly الخاصة بـSanctum، ولا يقبل Bearer Token للموبايل.";

  return `يختبر \`${route.method} /${route.uri}\` ضمن مجموعة ${folder}.

${authText}${permissionText}${mutationWarning}`;
}

function folderFor(route) {
  const uri = route.uri;

  if (uri === "sanctum/csrf-cookie" || uri.startsWith("api/web/auth/")) {
    return "Authentication";
  }
  if (
    uri.startsWith("api/createStaffMember") ||
    uri.startsWith("api/updateStaffMember") ||
    uri.startsWith("api/deleteStaffMember") ||
    uri.startsWith("api/getStaff") ||
    uri.startsWith("api/getAllStaff")
  ) {
    return "Staff";
  }
  if (uri.startsWith("api/role/")) return "Roles";
  if (uri.startsWith("api/project/")) return "Projects";
  if (uri.startsWith("api/mosque/")) return "Mosques";
  if (uri.startsWith("api/course/")) return "Courses";
  if (uri.startsWith("api/subject/")) return "Subjects";
  if (uri.startsWith("api/lesson/")) return "Lessons";
  if (uri.startsWith("api/courseDate/")) return "Course Dates";
  if (uri.startsWith("api/dateLesson/")) return "Curriculum";
  if (uri.startsWith("api/circle/")) return "Circles";
  if (uri.startsWith("api/studentCircle/")) return "Enrollments";
  if (uri.startsWith("api/student/")) return "Students";
  if (uri.startsWith("api/note/")) return "Notes";
  if (uri.startsWith("api/sabr/")) return "Sabrs";
  if (uri.startsWith("api/memorization/")) return "Memorizations";
  if (uri.startsWith("api/warning/")) return "Warnings";
  if (uri.startsWith("api/exam/")) return "Exams";
  if (uri.startsWith("api/absence/")) return "Absences";
  if (uri.startsWith("api/public/surveys/")) return "Public Surveys";
  if (uri.startsWith("api/surveys")) return "Surveys";
  if (
    uri === "api/courses-students" ||
    uri === "api/courses-dates-lessons" ||
    uri === "api/student-info"
  ) {
    return "Reports";
  }

  throw new Error(`No Bruno folder mapping for route: ${uri}`);
}

function requestName(route) {
  const key = `${route.method} ${route.uri}`;
  if (requestNameOverrides.has(key)) {
    return requestNameOverrides.get(key);
  }

  const action = route.action.split("@")[1] ?? route.uri.split("/").at(-1);
  const readable = action
    .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
    .replace(/^./, (character) => character.toUpperCase());

  return `${route.method} ${readable}`;
}

function safeFileName(name) {
  return name.replace(/[<>:"/\\|?*\u0000-\u001f]/g, "-");
}

function extractPathParameters(uri) {
  return [...uri.matchAll(/\{([^}]+)\}/g)].map((match) => ({
    name: match[1],
    value: environmentVariableForPathParameter(uri, match[1]),
  }));
}

function environmentVariableForPathParameter(uri, parameter) {
  const direct = {
    courseId: "courseId",
    courseDateId: "courseDateId",
    courseDate: "courseDateId",
    circleId: "circleId",
    studentId: "studentId",
    noteId: "noteId",
    publicToken: "publicSurveyToken",
    accessToken: "surveyFileAccessToken",
    survey: "surveyId",
    response: "surveyResponseId",
  };

  if (direct[parameter]) {
    return direct[parameter];
  }

  if (parameter !== "id") {
    return parameter;
  }

  const contextual = [
    ["absence/", "absenceId"],
    ["circle/", "circleId"],
    ["course/", "courseId"],
    ["courseDate/", "courseDateId"],
    ["dateLesson/", "courseDateId"],
    ["exam/", "examId"],
    ["lesson/", "lessonId"],
    ["memorization/", "memorizationId"],
    ["mosque/", "mosqueId"],
    ["project/", "projectId"],
    ["role/", "roleId"],
    ["sabr/", "sabrId"],
    ["student/", "studentId"],
    ["subject/", "subjectId"],
    ["warning/", "warningId"],
    ["Staff", "staffId"],
  ];

  return (
    contextual.find(([needle]) => uri.includes(needle))?.[1] ?? "staffId"
  );
}

function renderUrl(uri, pathParameters) {
  let path = uri;
  for (const parameter of pathParameters) {
    path = path.replace(`{${parameter.name}}`, `:${parameter.name}`);
  }
  return `{{baseUrl}}/${path}`;
}

function requestTags(route) {
  const tags = [isPublicRoute(route) ? "public" : "web-session"];

  if (route.method === "GET") {
    tags.push("read");
  } else {
    tags.push("write");
  }

  if (route.method === "DELETE") {
    tags.push("destructive");
  }

  return tags;
}

function isProtectedWebRoute(route) {
  return route.middleware.some((middleware) =>
    middleware.includes("EnsureAuthenticationChannel:web"),
  );
}

function isPublicRoute(route) {
  return !isProtectedWebRoute(route);
}

function isUnsafe(method) {
  return ["POST", "PUT", "PATCH", "DELETE"].includes(method);
}

function jsonBody(value) {
  const json = JSON.stringify(value, null, 2);
  return `  body:
    type: json
    data: |-
${json
  .split("\n")
  .map((line) => `      ${line}`)
  .join("\n")}`;
}

function indentDocumentation(text) {
  return text
    .split("\n")
    .map((line) => (line ? `  ${line}` : ""))
    .join("\n");
}

function yamlScalar(value) {
  return JSON.stringify(String(value));
}

function writeCoverage(webRoutes) {
  const byFolder = new Map();
  for (const route of webRoutes) {
    const folder = folderFor(route);
    byFolder.set(folder, (byFolder.get(folder) ?? 0) + 1);
  }

  const rows = folderConfiguration
    .filter(([folder]) => byFolder.has(folder))
    .map(([folder]) => `| ${folder} | ${byFolder.get(folder)} |`)
    .join("\n");

  writeFile(
    join(outputRoot, "ROUTE_COVERAGE.md"),
    `# Route coverage

Generated from \`php artisan route:list --json\`.

| Folder | Requests |
|---|---:|
${rows}
| **Total** | **${webRoutes.length}** |
`,
  );
}

function writeFile(path, content) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, content.endsWith("\n") ? content : `${content}\n`);
}
