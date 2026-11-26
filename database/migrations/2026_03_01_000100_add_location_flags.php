<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Seeded stand-in names (e.g. numbered kebeles) the zone is expected to rename.
            $table->boolean('is_placeholder')->default(false)->after('code');
            // false = the Amharic name is a best-guess expansion of a source abbreviation.
            $table->boolean('name_confirmed')->default(true)->after('is_placeholder');
        });

        DB::table('locations')->where('name_am', 'like', 'ናሙና ቀበሌ%')->update(['is_placeholder' => true]);
        DB::table('locations')->whereIn('code', ['YEM-SKAR', 'YEM-DSZ'])->update(['name_confirmed' => false]);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['is_placeholder', 'name_confirmed']);
        });
    }
};
