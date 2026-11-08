<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_movs', function (Blueprint $table) {
            $table->index(['office_target_id', 'user_id', 'created_at', 'id'], 'target_mov_owner_timeline');
            $table->index(['office_target_id', 'office_id', 'created_at', 'id'], 'target_mov_office_timeline');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notification_recipient_timeline');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notification_recipient_unread');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notification_recipient_timeline');
            $table->dropIndex('notification_recipient_unread');
        });
        Schema::table('target_movs', function (Blueprint $table) {
            $table->dropIndex('target_mov_owner_timeline');
            $table->dropIndex('target_mov_office_timeline');
        });
    }
};
