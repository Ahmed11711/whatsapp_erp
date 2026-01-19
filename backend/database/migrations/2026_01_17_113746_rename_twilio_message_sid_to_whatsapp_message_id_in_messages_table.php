<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if twilio_message_sid column exists before renaming
        if (Schema::hasColumn('messages', 'twilio_message_sid')) {
            // Use raw SQL to rename column (works across all database drivers)
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE messages CHANGE twilio_message_sid whatsapp_message_id VARCHAR(255) NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE messages RENAME COLUMN twilio_message_sid TO whatsapp_message_id');
            } elseif ($driver === 'sqlite') {
                // SQLite doesn't support RENAME COLUMN directly, need to recreate table
                Schema::table('messages', function (Blueprint $table) {
                    $table->string('whatsapp_message_id')->nullable()->after('status');
                });
                
                DB::statement('UPDATE messages SET whatsapp_message_id = twilio_message_sid');
                
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropColumn('twilio_message_sid');
                });
            } else {
                // Fallback: add new column, copy data, drop old column
                Schema::table('messages', function (Blueprint $table) {
                    $table->string('whatsapp_message_id')->nullable()->after('status');
                });
                
                DB::statement('UPDATE messages SET whatsapp_message_id = twilio_message_sid');
                
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropColumn('twilio_message_sid');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if whatsapp_message_id column exists before renaming back
        if (Schema::hasColumn('messages', 'whatsapp_message_id')) {
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE messages CHANGE whatsapp_message_id twilio_message_sid VARCHAR(255) NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE messages RENAME COLUMN whatsapp_message_id TO twilio_message_sid');
            } elseif ($driver === 'sqlite') {
                Schema::table('messages', function (Blueprint $table) {
                    $table->string('twilio_message_sid')->nullable()->after('status');
                });
                
                DB::statement('UPDATE messages SET twilio_message_sid = whatsapp_message_id');
                
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropColumn('whatsapp_message_id');
                });
            } else {
                Schema::table('messages', function (Blueprint $table) {
                    $table->string('twilio_message_sid')->nullable()->after('status');
                });
                
                DB::statement('UPDATE messages SET twilio_message_sid = whatsapp_message_id');
                
                Schema::table('messages', function (Blueprint $table) {
                    $table->dropColumn('whatsapp_message_id');
                });
            }
        }
    }
};
