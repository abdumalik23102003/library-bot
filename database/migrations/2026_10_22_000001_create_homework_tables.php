<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uy ishi: ustoz bergan vazifa. task/key - fayl ma'lumotlari nusxasi (material o'chirilsa ham AI uchun saqlanadi):
        // {kind, file_id, name, mime, size, text}
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('title');
            $table->text('note')->nullable();
            $table->json('task');
            $table->json('key')->nullable();
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->boolean('closed')->default(false);
            $table->text('report')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index('teacher_id');
        });

        // Har bir o'quvchi uchun bitta qator: holat, ball, AI izohi
        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending|submitted|checked|unreadable|error
            $table->unsignedSmallInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->json('mistakes')->nullable();
            $table->boolean('needs_teacher')->default(false);
            $table->text('reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_id', 'student_id']);
            $table->index('student_id');
        });

        // O'quvchi yuborgan yechim fayllari/matnlari (bir nechta bo'lishi mumkin)
        Schema::create('homework_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('homework_assignments')->cascadeOnDelete();
            $table->string('kind', 20); // text|photo|document|video|audio|voice
            $table->text('telegram_file_id')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_files');
        Schema::dropIfExists('homework_assignments');
        Schema::dropIfExists('homeworks');
    }
};
