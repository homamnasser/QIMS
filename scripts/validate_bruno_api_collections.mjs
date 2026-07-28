import { readFileSync, readdirSync } from "node:fs";
import { basename, dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = dirname(dirname(fileURLToPath(import.meta.url)));
const webRoot = join(projectRoot, "api", "Web User API");
const studentRoot = join(projectRoot, "api", "Student Self Service API");
const routes = loadLaravelRoutes();
const webRequests = loadRequests(webRoot);
const studentRequests = loadRequests(studentRoot);
const errors = [];

validateCoverage(
  "Web User API",
  routes.filter(isWebCollectionRoute),
  webRequests,
);
validateCoverage(
  "Student Self Service API",
  routes.filter((route) => route.uri.startsWith("api/mobile/")),
  studentRequests,
);
validateSequences(webRequests, true);
validateSequences(studentRequests, false);
validateWebSecurity(webRequests);
validateStudentSecurity(studentRequests);
validateNoEmbeddedSecretsOrLocalPaths([...webRequests, ...studentRequests]);

if (errors.length > 0) {
  console.error(`Bruno collection validation failed with ${errors.length} error(s):`);
  for (const error of errors) {
    console.error(`- ${error}`);
  }
  process.exitCode = 1;
} else {
  console.log(
    `Validated ${webRequests.length} web/public requests and ${studentRequests.length} mobile student requests.`,
  );
  console.log(`All ${webRequests.length + studentRequests.length} Laravel API routes are covered exactly once.`);
  console.log("Web requests use Cookie/CSRF authentication; mobile requests use the correct Bearer token type.");
}

function loadLaravelRoutes() {
  const raw = readFileSync(0, "utf8");
  if (!raw.trim()) {
    throw new Error(
      "Route JSON is required on stdin. Run: php artisan route:list --json | node scripts/validate_bruno_api_collections.mjs",
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
      middleware: route.middleware,
    }));
}

function loadRequests(root) {
  return walkFiles(root)
    .filter((path) => path.endsWith(".yml"))
    .map((path) => ({ path, content: readFileSync(path, "utf8") }))
    .filter(({ content }) => /^  type: http$/m.test(content))
    .map(({ path, content }) => {
      const method = content.match(/^  method:\s*(\S+)/m)?.[1];
      const rawUrl = content.match(/^  url:\s*(.+)$/m)?.[1]?.trim();
      const sequence = Number(content.match(/^  seq:\s*(\d+)/m)?.[1]);

      if (!method || !rawUrl || !Number.isInteger(sequence)) {
        errors.push(`Invalid HTTP request structure: ${relative(projectRoot, path)}`);
      }

      return {
        path,
        content,
        method,
        sequence,
        uri: normalizeRequestUrl(rawUrl ?? ""),
      };
    });
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

function normalizeRequestUrl(rawUrl) {
  return rawUrl
    .replace(/^["']|["']$/g, "")
    .replace(/^\{\{baseUrl\}\}\//, "")
    .split("?")[0]
    .replace(/:[A-Za-z_][A-Za-z0-9_]*/g, "{}")
    .replace(/\/+$/, "");
}

function normalizeLaravelUri(uri) {
  return uri.replace(/\{[^}]+\}/g, "{}").replace(/\/+$/, "");
}

function routeKey(method, uri) {
  return `${method} ${uri}`;
}

function validateCoverage(label, expectedRoutes, requests) {
  const expected = expectedRoutes.map((route) =>
    routeKey(route.method, normalizeLaravelUri(route.uri)),
  );
  const actual = requests.map((request) =>
    routeKey(request.method, request.uri),
  );

  for (const duplicate of duplicates(actual)) {
    errors.push(`${label} contains duplicate route: ${duplicate}`);
  }
  for (const key of expected.filter((candidate) => !actual.includes(candidate))) {
    errors.push(`${label} is missing route: ${key}`);
  }
  for (const key of actual.filter((candidate) => !expected.includes(candidate))) {
    errors.push(`${label} contains an unknown route: ${key}`);
  }
}

function validateSequences(requests, perFolder) {
  const grouped = new Map();
  for (const request of requests) {
    const key = perFolder ? dirname(request.path) : "collection";
    const group = grouped.get(key) ?? [];
    group.push(request);
    grouped.set(key, group);
  }

  for (const [group, groupRequests] of grouped) {
    const sequences = groupRequests
      .map((request) => request.sequence)
      .sort((left, right) => left - right);
    const expected = Array.from(
      { length: groupRequests.length },
      (_value, index) => index + 1,
    );
    if (JSON.stringify(sequences) !== JSON.stringify(expected)) {
      errors.push(
        `Non-contiguous request sequence in ${relative(projectRoot, group)}: ${sequences.join(", ")}`,
      );
    }

    for (const request of groupRequests) {
      const filePrefix = Number(basename(request.path).match(/^(\d+)/)?.[1]);
      if (filePrefix !== request.sequence) {
        errors.push(
          `Filename and seq disagree: ${relative(projectRoot, request.path)}`,
        );
      }
    }
  }
}

function validateWebSecurity(requests) {
  for (const request of requests) {
    const label = relative(projectRoot, request.path);
    if (/^  auth:\n\s+type: bearer/m.test(request.content)) {
      errors.push(`Web request must not use Bearer authentication: ${label}`);
    }
    if (/^\s+- name: Authorization$/m.test(request.content)) {
      errors.push(`Web request must not send Authorization: ${label}`);
    }
    if (!/^\s+- name: Origin\n\s+value: "\{\{webOrigin\}\}"$/m.test(request.content)) {
      errors.push(`Web request is missing the configured Origin header: ${label}`);
    }
    if (
      ["POST", "PUT", "PATCH", "DELETE"].includes(request.method) &&
      !/^\s+- name: X-XSRF-TOKEN\n\s+value: "\{\{xsrfToken\}\}"$/m.test(
        request.content,
      )
    ) {
      errors.push(`Unsafe web request is missing X-XSRF-TOKEN: ${label}`);
    }
  }

  const csrf = findRequest(requests, "GET", "sanctum/csrf-cookie");
  const login = findRequest(requests, "POST", "api/web/auth/login");
  if (!csrf?.content.includes('bru.cookies.get("XSRF-TOKEN")')) {
    errors.push("The web CSRF request does not capture XSRF-TOKEN.");
  }
  if (!login?.content.includes('bru.cookies.has("qims_web_session")')) {
    errors.push("The web login request does not verify the HttpOnly session cookie.");
  }
  if (!login?.content.includes('to.not.have.property("access_token")')) {
    errors.push("The web login request does not assert that no access token is returned.");
  }
}

function validateStudentSecurity(requests) {
  for (const request of requests) {
    const label = relative(projectRoot, request.path);
    const isLogin =
      request.method === "POST" && request.uri === "api/mobile/auth/login";
    const tokenVariable =
      request.method === "POST" && request.uri === "api/mobile/auth/refresh"
        ? "refreshToken"
        : "studentToken";

    if (isLogin) {
      if (/^  auth:/m.test(request.content)) {
        errors.push(`Mobile login must not require an existing token: ${label}`);
      }
      continue;
    }

    if (!/^  auth:\n\s+type: bearer$/m.test(request.content)) {
      errors.push(`Protected mobile request is missing Bearer auth: ${label}`);
    }
    if (!request.content.includes(`token: "{{${tokenVariable}}}"`)) {
      errors.push(
        `Mobile request uses the wrong token variable (${tokenVariable} expected): ${label}`,
      );
    }
    if (/^\s+- name: Cookie$/m.test(request.content)) {
      errors.push(`Mobile request must not send web Cookies: ${label}`);
    }
  }
}

function validateNoEmbeddedSecretsOrLocalPaths(requests) {
  for (const request of requests) {
    const label = relative(projectRoot, request.path);
    if (/\b\d+\|[A-Za-z0-9]{20,}\b/.test(request.content)) {
      errors.push(`Embedded Sanctum token found: ${label}`);
    }
    if (/(?:[A-Za-z]:\\Users\\|\/home\/[^/\s]+\/)/.test(request.content)) {
      errors.push(`Embedded user-specific filesystem path found: ${label}`);
    }
  }
}

function findRequest(requests, method, uri) {
  return requests.find(
    (request) => request.method === method && request.uri === uri,
  );
}

function duplicates(values) {
  return [...new Set(values.filter((value, index) => values.indexOf(value) !== index))];
}

function isWebCollectionRoute(route) {
  if (route.uri.startsWith("api/mobile/")) {
    return false;
  }
  if (
    route.uri === "sanctum/csrf-cookie" ||
    route.uri.startsWith("api/web/auth/") ||
    route.uri.startsWith("api/public/surveys/")
  ) {
    return true;
  }
  return route.middleware.some((middleware) =>
    middleware.includes("EnsureAuthenticationChannel:web"),
  );
}
