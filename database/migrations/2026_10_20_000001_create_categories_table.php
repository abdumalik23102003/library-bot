<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete(); // null = default kategoriya
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_access', 20)->default('public');
            $table->boolean('is_default')->default(false);
            $table->string('system_type', 20)->nullable(); // default kategoriya qaysi material turiga mos
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_default', 'system_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
