<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('report_type');
            $table->unsignedBigInteger('report_id');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_name');
            $table->string('path');
            $table->timestamps();
            $table->index(['report_type', 'report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
