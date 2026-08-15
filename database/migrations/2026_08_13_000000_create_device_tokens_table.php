<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            // Student أو User: التطبيق نفسه يخدم الطالب والموظف، والعمود المورفي
            // بنفس كلفة عمود student_id ويغطي الموظف لاحقًا بلا هجرة ثانية.
            $table->morphs('tokenable');
            // الرمز فريد عالميًا: جهاز واحد = صف واحد، وتسجيل دخول طالب آخر على
            // الجهاز نفسه ينقل ملكية الصف بدل أن يُنشئ صفًّا يتيمًا يُشعر الطالب السابق.
            $table->string('token', 255)->unique();
            $table->string('device_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
