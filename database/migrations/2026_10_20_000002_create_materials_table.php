<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // null = tizim materiali
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('type', 20); // document|video|photo|audio|voice|link|text
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('telegram_file_id')->nullable();
            $table->string('telegram_file_unique_id')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('url')->nullable();
            $table->text('body')->nullable();
            $table->string('access', 20)->default('public'); // public|protected|private
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('category_id');
            $table->index('access');
            $table->index('type');
        });

        // Qidiruv uchun trigram indekslari (ILIKE '%...%' tez ishlashi uchun)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX materials_title_trgm_idx ON materials USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX materials_description_trgm_idx ON materials USING gin (description gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
