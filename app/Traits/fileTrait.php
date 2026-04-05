<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait FileTrait
{
    /**
     * تخزين ملف واحد (صورة، PDF، إلخ)
     * * @param UploadedFile $file الملف المرفوع من الريكوست
     * @param string $folder اسم المجلد داخل storage/app/public
     * @return string مسار الملف المخزن
     */
    public function saveFile(UploadedFile $file, string $folder = 'uploads'): string
    {
        // 1. توليد اسم فريد للملف باستخدام الوقت ومعرف فريد لمنع تكرار الأسماء
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        // 2. التخزين باستخدام storeAs (يتعامل مع الصور والـ PDF وكل الأنواع)
        // سيتم التخزين في: storage/app/public/$folder/$fileName
        return $file->storeAs($folder, $fileName, 'public');
    }

    /**
     * تخزين مجموعة ملفات (مثل مصفوفة صور أو مستندات)
     * * @param array $files مصفوفة من الملفات المرفوعة
     * @param string $folder المجلد المستهدف
     * @return array مصفوفة تحتوي على مسارات الملفات المخزنة
     */
    public function uploadMultipleFiles(array $files, string $folder = 'uploads'): array
    {
        $paths = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $this->saveFile($file, $folder);
            }
        }
        return $paths;
    }

    public function deleteFile(?string $path): bool
    {
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }
        return false;
    }
}
