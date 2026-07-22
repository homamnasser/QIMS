<?php

namespace App\Support;

use App\Enums\RoleFamily;

/**
 * Suggests a stable role family from a human-readable role name.
 *
 * This resolver is used only while creating/backfilling roles. Runtime
 * authorization must use the stored role_family and Spatie permissions.
 */
class RoleNameResolver
{
    /**
     * Negated or explicitly non-authoritative wording must fail closed.
     * The resolver is advisory only, but avoiding an obviously misleading
     * suggestion keeps the role-authoring experience safe and predictable.
     *
     * @var array<int, string>
     */
    private const NEGATION_TOKENS = [
        'غير',
        'ليس',
        'ليست',
        'بدون',
        'دون',
        'لا',
        'not',
        'non',
        'no',
        'without',
    ];

    public function resolve(string $name): RoleFamily
    {
        $normalized = $this->normalize($name);

        $canonicalFamilies = [
            'super admin' => RoleFamily::SuperAdmin,
            'superadmin' => RoleFamily::SuperAdmin,
            'admin' => RoleFamily::Admin,
            'supervisor' => RoleFamily::Supervisor,
            'teacher' => RoleFamily::Teacher,
            'student' => RoleFamily::Student,
        ];

        if (isset($canonicalFamilies[$normalized])) {
            return $canonicalFamilies[$normalized];
        }

        if ($this->containsNegation($normalized)) {
            return RoleFamily::Custom;
        }

        // Admin is checked before supervisor because admin roles are
        // supervisory-capable while retaining their own semantic family.
        if ($this->containsAnyToken($normalized, ['admin', 'administrator', 'اداري'])) {
            return RoleFamily::Admin;
        }

        if ($this->containsAnyToken($normalized, [
            'supervisor',
            'مشرف',
            'مشرفون',
            'مسؤول',
            'مسئول',
            'مسوول',
            'مسؤولون',
            'مدير',
            'مدراء',
        ])) {
            return RoleFamily::Supervisor;
        }

        if ($this->containsAnyToken($normalized, ['teacher', 'instructor', 'معلم', 'مدرس', 'استاذ'])) {
            return RoleFamily::Teacher;
        }

        if ($this->containsAnyToken($normalized, ['student', 'pupil', 'طالب', 'طلاب', 'طالبات', 'تلميذ', 'تلاميذ'])) {
            return RoleFamily::Student;
        }

        return RoleFamily::Custom;
    }

    public function normalize(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = str_replace('ـ', '', $normalized);
        $normalized = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $normalized) ?? $normalized;
        $normalized = strtr($normalized, [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
        ]);
        $normalized = preg_replace('/[_\-.]+/u', ' ', $normalized) ?? $normalized;

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    /**
     * @param  array<int, string>  $needles
     */
    private function containsAnyToken(string $haystack, array $needles): bool
    {
        $tokens = preg_split('/\s+/u', $haystack, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($needles as $needle) {
            if (in_array($this->normalize($needle), $tokens, true)) {
                return true;
            }
        }

        return false;
    }

    private function containsNegation(string $normalized): bool
    {
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count(array_intersect($tokens, self::NEGATION_TOKENS)) > 0;
    }
}
