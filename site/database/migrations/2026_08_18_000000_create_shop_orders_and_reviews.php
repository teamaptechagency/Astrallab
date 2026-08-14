<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What this site keeps about a sale, and what customers say afterwards.
 *
 * The order itself belongs to the hub — it decides whether it was paid and it
 * issues the licence. What is kept here is the customer's side of it: the
 * address they can come back to, and the key, because the hub hands that over
 * exactly once and then forgets it.
 *
 * That last point is the reason this table exists at all. Without it, a
 * customer who closes the tab before copying their key has lost it for good,
 * and no amount of asking us can bring it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();

            // The hub's reference. This is what the customer's order page is
            // addressed by, and what they quote when they write in.
            $table->string('reference', 20)->unique();

            $table->string('product_slug', 80);
            $table->string('product_name', 120);
            $table->unsignedBigInteger('amount');

            $table->string('customer_name', 120);
            $table->string('customer_email', 190);

            // Collected from the hub the first time this order is looked at
            // after the payment is accepted, and kept — because the hub cannot
            // produce it a second time.
            $table->text('licence_key')->nullable();
            $table->timestamp('key_saved_at')->nullable();

            // What the hub last said. Not authoritative — the hub is — but it
            // saves asking on every page load.
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            $table->index('customer_email');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // The product as the hub names it. Not a foreign key: the catalogue
            // lives on the hub, and a review should survive a product being
            // renamed or retired there.
            $table->string('product_slug', 80);

            $table->string('name', 120);
            $table->string('email', 190)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('body');

            // Nothing appears until somebody has read it. An unmoderated review
            // form on a public site is a spam board within a week.
            $table->boolean('approved')->default(false);

            // Set when the reviewer's email matches an order. Shown as
            // "verified purchase", which is the only claim worth making about
            // a review and the only one this site can actually check.
            $table->boolean('verified')->default(false);

            $table->timestamps();

            $table->index(['product_slug', 'approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('shop_orders');
    }
};
