<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talking to manage.astrallabs.uk.
 *
 * This site sells. It does not decide that anybody has paid, it does not hold
 * the signing key, and it cannot mint a licence — all of that lives on the hub,
 * behind a secret this site carries and a human who checks the money arrived.
 *
 * That split is the whole design. This application is on the public internet
 * with a shop on it; if it could issue licences, anybody who got into it could
 * issue licences.
 */
class Hub
{
    private static function base(): string
    {
        return rtrim((string) config('astralab.hub_url'), '/');
    }

    /**
     * What is for sale, and how it may be paid for.
     *
     * Cached, because this is on the front page and the hub going quiet for a
     * minute should not take the shop down with it. Five minutes is short
     * enough that a price change is live before anybody notices, and long
     * enough that a burst of visitors is one request.
     *
     * @return array{ok: bool, products: array<int, array<string, mixed>>, payment_methods: array<int, array<string, mixed>>}
     */
    public static function catalogue(): array
    {
        $empty = ['ok' => false, 'products' => [], 'payment_methods' => []];

        if (self::base() === '') {
            return $empty;
        }

        try {
            return Cache::remember('hub.catalogue', now()->addMinutes(5), function () {
                $response = Http::timeout(6)->acceptJson()->get(self::base().'/api/v1/catalogue');

                if (! $response->successful()) {
                    // Thrown rather than cached, so a bad minute is not
                    // remembered for five.
                    throw new \RuntimeException('The hub answered '.$response->status());
                }

                return [
                    'ok' => true,
                    'products' => $response->json('products', []),
                    'payment_methods' => $response->json('payment_methods', []),
                ];
            });
        } catch (Throwable $e) {
            // Logged, not shown. A visitor gets a page saying the shop cannot
            // take orders at the moment, which is true and is not their
            // problem to debug.
            Log::warning('The hub catalogue could not be read: '.$e->getMessage());

            return $empty;
        }
    }

    /**
     * Does the hub accept our secret?
     *
     * Asked by requesting an order that does not exist. A wrong secret is
     * refused before the hub ever looks for it, so 401 means "not this shop"
     * and 404 means "this shop, no such order" — which is the answer we want.
     *
     * Deliberately not a matter of comparing strings here: the only opinion
     * that counts is the hub's.
     */
    public static function secretAccepted(): bool
    {
        try {
            $response = Http::timeout(8)
                ->withToken((string) config('astralab.store_secret'))
                ->acceptJson()
                ->get(self::base().'/api/v1/orders/AL-CONNECTION-TEST');

            return $response->status() !== 401;
        } catch (Throwable $e) {
            Log::warning('The hub could not be asked about the store secret: '.$e->getMessage());

            return false;
        }
    }

    /** One product out of the catalogue, or null. */
    public static function product(string $slug): ?array
    {
        foreach (self::catalogue()['products'] as $product) {
            if (($product['slug'] ?? null) === $slug) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Place the order and the payment the customer says they have made.
     *
     * Nothing is issued by this call. It comes back pending, and the order page
     * says so — which is the truth: somebody is about to check whether the
     * money arrived.
     *
     * @param  array<string, mixed>  $order
     * @return array{ok: bool, reference: ?string, message: string}
     */
    public static function placeOrder(array $order): array
    {
        try {
            $response = Http::timeout(12)
                ->withToken((string) config('astralab.store_secret'))
                ->acceptJson()
                ->post(self::base().'/api/v1/orders', $order);
        } catch (Throwable $e) {
            Log::error('An order could not be sent to the hub: '.$e->getMessage());

            return [
                'ok' => false,
                'reference' => null,
                'message' => 'We could not reach our order system just now. Nothing has been taken — '
                    .'please try again in a moment, or message us and we will finish it by hand.',
            ];
        }

        if ($response->status() === 422) {
            // The hub's own words. It is the thing that knows a transaction
            // number has been used before.
            return [
                'ok' => false,
                'reference' => null,
                'message' => $response->json('message') ?: 'That order could not be accepted.',
            ];
        }

        if (! $response->successful()) {
            Log::error('The hub refused an order: '.$response->status().' '.$response->body());

            return [
                'ok' => false,
                'reference' => null,
                'message' => 'Our order system refused that. Please message us — we will sort it out and '
                    .'you will not be charged twice.',
            ];
        }

        return ['ok' => true, 'reference' => $response->json('reference'), 'message' => ''];
    }

    /**
     * Where an order has got to.
     *
     * The licence key appears in this answer exactly once, the first time the
     * hub is asked after a payment is accepted. Whoever calls this is
     * responsible for keeping it — ask a second time and it is gone, because
     * the hub only ever stored a hash of it.
     *
     * @return array<string, mixed>|null
     */
    public static function order(string $reference): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withToken((string) config('astralab.store_secret'))
                ->acceptJson()
                ->get(self::base().'/api/v1/orders/'.urlencode($reference));

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('An order could not be read from the hub: '.$e->getMessage());

            return null;
        }
    }
}
