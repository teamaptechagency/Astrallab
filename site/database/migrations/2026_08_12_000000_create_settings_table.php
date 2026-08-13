<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings a person can change without a file manager.
 *
 * The contact details, the company name and the refund window lived in .env,
 * which meant changing a phone number required opening a hosting control
 * panel, finding a hidden file, and editing it without breaking the syntax. On
 * a live business that is not a settings system; it is a reason the phone
 * number stays wrong.
 *
 * Key and value, deliberately: these are a handful of strings read together on
 * every page, and a column per setting would mean a migration every time a
 * page needs one more line of text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            // The key is the primary key. There is exactly one row per setting
            // and no reason for an id nobody will ever refer to.
            $table->string('key', 120)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
