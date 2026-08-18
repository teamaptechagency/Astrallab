<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ShopOrder;
use App\Support\Hub;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Selling, from the customer's side.
 *
 * The catalogue, the prices and the payment methods all come from the hub, so
 * there is one place a price is decided and it is not this one. This site
 * takes the order, tells the customer what happens next, and — once the hub
 * says the payment was accepted — hands over the key and keeps a copy, because
 * the hub gives it up exactly once.
 */
class ShopController extends Controller
{
    /**
     * The front page, with the catalogue already in it.
     *
     * It used to fetch this from the browser, across origins, from an address
     * that never existed — /api/public/products, a name from the plan rather
     * than from the routing table. So the one section that says what this
     * company sells has been apologising for a catalogue outage that was not
     * happening.
     *
     * Rendered here instead, by the same call the shop page makes. No second
     * address to keep in step, no cross-origin request to be blocked, and the
     * products are in the HTML for anybody whose JavaScript never runs —
     * including whatever reads the page for search results.
     */
    public function home()
    {
        return view('pages.home', ['catalogue' => Hub::catalogue()]);
    }

    /** What is for sale. */
    public function index()
    {
        $catalogue = Hub::catalogue();

        return view('shop.index', [
            'catalogue' => $catalogue,
            'ratings' => collect($catalogue['products'])
                ->mapWithKeys(fn ($product) => [$product['slug'] => Review::summary($product['slug'])]),
        ]);
    }

    /** One product, its reviews, and the way to buy it. */
    public function product(string $slug)
    {
        $product = Hub::product($slug);

        abort_if(! $product, 404);

        return view('shop.product', [
            'product' => $product,
            'methods' => Hub::catalogue()['payment_methods'],
            'reviews' => Review::shown($slug)->limit(20)->get(),
            'summary' => Review::summary($slug),
        ]);
    }

    /**
     * Take the order.
     *
     * The amount is not accepted from this form. The hub charges its own price
     * — the browser is not a source of truth about what something costs.
     */
    public function place(Request $request, string $slug)
    {
        $product = Hub::product($slug);

        abort_if(! $product, 404);

        $methods = collect(Hub::catalogue()['payment_methods'])->pluck('key');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'method' => ['required', Rule::in($methods)],
            'sender' => ['nullable', 'string', 'max:40'],
            // The one thing only the customer knows, and the one thing the hub
            // will check.
            'reference' => ['required', 'string', 'max:60'],
        ], [
            'reference.required' => 'Please enter the transaction number from your payment confirmation.',
        ]);

        $result = Hub::placeOrder($data + [
            'product' => $slug,
            'amount' => (int) $product['price'],
        ]);

        if (! $result['ok']) {
            return back()->withInput()->with('problem', $result['message']);
        }

        // Kept here as well, because this is the address the customer comes
        // back to and the place their key will live.
        ShopOrder::create([
            'reference' => $result['reference'],
            'product_slug' => $slug,
            'product_name' => $product['name'],
            'amount' => (int) $product['price'],
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'status' => 'pending',
        ]);

        return redirect()->route('order', $result['reference']);
    }

    /**
     * Where an order stands, and the key once there is one.
     *
     * Asked of the hub on every load until the key has been collected, and not
     * afterwards — the answer stops changing and the key is already saved.
     */
    public function order(string $reference)
    {
        $order = ShopOrder::where('reference', $reference)->firstOrFail();

        if (! $order->licence_key) {
            $order->refreshFromHub();
        }

        return view('shop.order', ['order' => $order]);
    }

    /**
     * The installer, handed to somebody who has bought.
     *
     * Sent as a download and never rendered — it is a .php file, and served
     * inline it is the sort of thing a browser offers to open and a proxy
     * decides to have an opinion about.
     */
    public function installer(string $slug)
    {
        abort_if(! Hub::product($slug), 404);

        $installer = Hub::installer($slug);

        if (! $installer['ok']) {
            // Back where they came from with the reason, rather than a bare
            // error page. Somebody who has just paid should not be looking at a
            // stack trace.
            return back()->with('problem', $installer['message']);
        }

        return response($installer['source'], 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$installer['filename'].'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * A review, held back until somebody has read it.
     *
     * Marked as a verified purchase when the email matches an order — the only
     * claim about a review this site can actually check, and therefore the only
     * one worth making.
     */
    public function review(Request $request, string $slug)
    {
        abort_if(! Hub::product($slug), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'body.min' => 'Please say a little more — a line or two helps somebody deciding.',
        ]);

        $review = Review::create($data + ['product_slug' => $slug]);

        if (! empty($data['email'])) {
            $bought = ShopOrder::where('customer_email', $data['email'])
                ->where('product_slug', $slug)
                ->where('status', 'paid')
                ->exists();

            if ($bought) {
                $review->forceFill(['verified' => true])->save();
            }
        }

        return back()->with('ok', 'Thank you — your review will appear once we have read it.');
    }
}
