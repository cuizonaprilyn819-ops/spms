<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_department_id')->nullable()->unique()->after('id');
            $table->unsignedInteger('campus')->nullable()->after('description');
            $table->string('department_head_reference')->nullable()->after('campus');
            $table->string('supervisor_reference')->nullable()->after('department_head_reference');
            $table->boolean('is_active')->default(true)->after('supervisor_reference');
        });

        Schema::create('position_titles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_csc_item_id')->unique();
            $table->string('title');
            $table->string('description', 255)->nullable();
            $table->string('salary_grade', 15)->nullable();
            $table->unsignedInteger('campus')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'title']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_title_id')
                ->nullable()
                ->after('office_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_title_id');
        });

        Schema::dropIfExists('position_titles');

        Schema::table('offices', function (Blueprint $table) {
            $table->dropUnique(['legacy_department_id']);
            $table->dropColumn([
                'legacy_department_id',
                'campus',
                'department_head_reference',
                'supervisor_reference',
                'is_active',
            ]);
        });
    }
};
