<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O'quvchining ustozga qo'shilish so'rovi / aloqasi
        Schema::create('teacher_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|removed
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'student_id']);
            $table->index('status');
        });

        // Ustozning protected materiallariga kirish so'rovi / ruxsati
        Schema::create('protected_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|removed
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'student_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protected_accesses');
        Schema::dropIfExists('teacher_student');
    }
};
