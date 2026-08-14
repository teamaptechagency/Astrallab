<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What has been done to this console, and by whom.
 *
 * Every other kind of change here is already accountable — a licence carries
 * its own history, and a release records who published it. The one thing that
 * was not written down anywhere is the console replacing its own code and
 * rewriting its own database, which is the change that can break all of it.
 *
 * The question this exists to answer is asked in the past tense, weeks later,
 * by somebody looking at behaviour nobody expected: what changed, and when.
 * Without it the only evidence is a file modification time, and the update
 * mechanism rewrites those by definition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_log', function (Blueprint $table) {
            $table->id();

            // build — new files were put in place
            // database — migrations were run
            $table->string('kind', 20);

            // One line, already readable. This table is read by a person under
            // time pressure, not queried.
            $table->string('summary', 190);

            // The names of the migrations, or what the archive was called.
            $table->text('detail')->nullable();

            // Kept as a name rather than an id, so it survives the operator
            // being deactivated or removed. An update attributed to a deleted
            // account is attributed to nobody.
            $table->string('actor', 120)->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_log');
    }
};
