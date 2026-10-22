<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeworks', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('closed');
        });

        DB::table('homeworks')->where('closed', true)->update(['closed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
