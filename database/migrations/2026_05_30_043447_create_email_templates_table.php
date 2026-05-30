<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();      // event key, e.g. run_scheduled
                $table->string('name');
                $table->string('subject');
                $table->text('body_html');             // supports {tokens}
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }
        // add an optional html column + template key to queued_emails
        if (Schema::hasTable('queued_emails')) {
            Schema::table('queued_emails', function (Blueprint $table) {
                if (! Schema::hasColumn('queued_emails', 'body_html')) $table->text('body_html')->nullable()->after('body');
                if (! Schema::hasColumn('queued_emails', 'template_key')) $table->string('template_key')->nullable()->after('body_html');
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        if (Schema::hasTable('queued_emails')) {
            Schema::table('queued_emails', function (Blueprint $table) {
                if (Schema::hasColumn('queued_emails', 'body_html')) $table->dropColumn('body_html');
                if (Schema::hasColumn('queued_emails', 'template_key')) $table->dropColumn('template_key');
            });
        }
    }
};
