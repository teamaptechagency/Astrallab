<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The licence hub, as tables.
 *
 * A faithful port of the Prisma schema this replaces, with the reasoning kept
 * rather than dropped — the comments are why each column is shaped the way it
 * is, and losing them in the move would make the next change guesswork.
 *
 * Two deliberate differences from the original. Money is stored in the
 * smallest unit as an integer rather than a float, because a float that has
 * been through a few sums stops adding up and this table is what revenue is
 * reported from. And the string enums stay strings, as they were: a real enum
 * needs a migration to add a status, which is exactly what you want to do
 * quickly during an incident.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- what we sell ----

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // The stable machine key an installer sends, e.g. "astralab-cms".
            // Never changed once anything is sold: every install in the field
            // sends this string on every request.
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            // Internal note; never leaves the console.
            $table->text('description')->nullable();
            // Customer-facing copy for the public catalogue. Separate, so an
            // internal remark cannot end up on the storefront by accident.
            $table->text('summary')->nullable();
            // Retired products keep working for existing installs — activation
            // and updates still function — but the store stops selling them.
            // Nothing is deleted, because licences reference it forever.
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version', 40);
            $table->string('channel', 20)->default('stable');
            // normal | security — a security release shortens the client's
            // check interval and shows a persistent warning rather than a
            // banner that can be dismissed and forgotten.
            $table->string('severity', 20)->default('normal');
            $table->text('notes')->nullable();
            // Where the package actually lives. Never handed to a client:
            // they receive a signed, expiring link derived from this.
            $table->string('package_url', 500);
            // SHA-256, so an installer can check what it downloaded before
            // extracting it over a live shop.
            $table->string('checksum', 64);
            $table->unsignedBigInteger('size_bytes')->default(0);
            // The lowest version that may jump straight to this one. Anything
            // older comes up through the intermediate releases, so migrations
            // run in the order they were written.
            $table->string('min_upgrade_from', 40)->nullable();
            // Unpublished releases are invisible to clients — a staging area
            // for an upload that is not ready to reach anybody.
            $table->boolean('published')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'published']);
        });

        // ---- who bought it ----

        Schema::create('licences', function (Blueprint $table) {
            $table->id();
            // The key itself is never stored, only its HMAC — exactly like a
            // session token. A dump of this table cannot be turned back into
            // working keys.
            $table->string('key_hash', 64)->unique();
            // Kept separately so support can find a licence a customer reads
            // out over the phone.
            $table->string('key_last4', 8);
            $table->foreignId('product_id')->constrained();

            // Where the sale came from. Unique as a pair, so a webhook
            // delivered twice — which WooCommerce does routinely — cannot mint
            // a second licence for one order.
            $table->string('order_ref', 100);
            $table->string('order_source', 40)->default('woocommerce');

            $table->string('customer_email', 190);
            $table->string('customer_name', 190)->nullable();

            // What was actually paid, in poisha, as the store reported it at
            // issue time. Stored rather than derived, so revenue reporting
            // cannot be silently rewritten by a change to today's list price.
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('currency', 3)->default('BDT');

            // unactivated — paid, never bound to a domain
            // active      — bound to a production domain
            // deactivated — released by the customer, free to bind elsewhere
            // suspended   — payment dispute or abuse; reversible
            // revoked     — refund or fraud; terminal
            $table->string('status', 20)->default('unactivated');
            // Why a person changed it. Shown to the customer for suspended and
            // revoked, so the message is not a dead end.
            $table->text('status_note')->nullable();

            // How many domains may be bound at once. One, per the sales terms;
            // stored rather than hardcoded so a multi-site tier can exist later
            // without a migration.
            $table->unsignedSmallInteger('seat_limit')->default(1);

            $table->timestamps();

            $table->unique(['order_source', 'order_ref']);
            $table->index('customer_email');
            $table->index('status');
        });

        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licence_id')->constrained()->cascadeOnDelete();

            // Host only — no scheme, no www, no trailing slash, lowercased.
            // Normalised before storage, so "https://WWW.Shop.com/" and
            // "shop.com" can never take two seats.
            $table->string('domain', 190);

            $table->timestamp('released_at')->nullable();
            // What the install last reported. Used by support, and by the
            // upgrade path calculation.
            $table->string('last_version', 40)->nullable();
            $table->string('php_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('licence_id');
            $table->index('domain');
        });

        // Append-only. Support and abuse review both run off this, and nothing
        // in it is ever updated in place.
        Schema::create('licence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licence_id')->constrained()->cascadeOnDelete();
            // issued | activated | deactivated | blocked | downloaded | status_changed
            $table->string('kind', 30);
            $table->string('domain', 190)->nullable();
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            // The operator's name rather than their id, so the record still
            // reads correctly after an account is renamed or deactivated. An
            // audit trail should say what was true at the time.
            $table->string('actor', 190)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['licence_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });

        // ---- support ----

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            // Kept when the licence goes: a report about a bug is still a bug
            // after the customer has left.
            $table->foreignId('licence_id')->nullable()->constrained()->nullOnDelete();

            $table->string('domain', 190)->nullable();
            $table->string('kind', 20)->default('bug');
            $table->string('subject', 200);
            $table->text('body');

            // Captured automatically. Free text — whatever the install
            // reported, and never to be trusted for authorisation.
            $table->string('cms_version', 40)->nullable();
            $table->string('php_version', 40)->nullable();

            $table->string('status', 20)->default('open');
            $table->string('severity', 20)->default('normal');
            // The version a fix shipped in, once there is one.
            $table->string('fixed_in', 40)->nullable();
            $table->text('response')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('licence_id');
        });

        // ---- data pushed up from customer shops ----

        Schema::create('synced_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licence_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 190);

            // The product's id in the customer's own database. Unique per
            // licence, so a re-sync updates rather than duplicating.
            $table->string('external_id', 100);
            $table->string('name', 250);
            $table->unsignedBigInteger('price')->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->integer('stock')->nullable();
            // Pointed at the customer's own server rather than copied, which
            // keeps storage cost near zero.
            $table->string('image_url', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('category', 120)->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['licence_id', 'external_id']);
            $table->index('name');
        });

        // Personal data belonging to the customer's customers — people with no
        // relationship to us at all. Only stored for installs that explicitly
        // opted in; the sync endpoint refuses it otherwise.
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licence_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 190);

            $table->string('external_id', 100);
            $table->string('name', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            // order | enquiry | newsletter | abandoned_cart
            $table->string('source', 30)->default('enquiry');
            $table->text('note')->nullable();
            $table->timestamp('captured_at')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['licence_id', 'external_id']);
        });

        // ---- money ----

        // Licence sales are NOT in here — they are derived from licences.amount,
        // so the two can never disagree. This is for everything the hub cannot
        // know by itself: hosting bills, contractors, refunds, ad spend.
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            // Poisha, like everywhere else. A float that has been through a few
            // sums stops adding up, and this is what the numbers come from.
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('BDT');
            $table->string('category', 30)->default('other');
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
            $table->index(['kind', 'occurred_at']);
        });
    }

    public function down(): void
    {
        // Reverse order: the children reference the parents.
        foreach ([
            'transactions', 'leads', 'synced_products', 'reports',
            'licence_events', 'activations', 'licences', 'releases', 'products',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
