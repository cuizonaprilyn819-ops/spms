<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('Planning Officer', 'web');
        Schema::table('evaluation_cycles', function (Blueprint $table) {
            $table->foreignId('pmt_chairperson_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_cycles', fn (Blueprint $table) => $table->dropConstrainedForeignId('pmt_chairperson_id'));
    }
};
