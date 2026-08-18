<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The front page says what this company sells.
 *
 * Written after it spent its whole life apologising for an outage that was not
 * happening: the grid was filled from the browser, across origins, from
 * /api/public/products — an address that has never existed. Nothing failed
 * loudly. The section simply stayed empty, and no test looked at it because no
 * test rendered the page.
 *
 * So these render it.
 */
class HomeCatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['astralab.hub_url' => 'https://hub.test']);

        // The catalogue is cached for five minutes, which between tests means
        // one test's fake answering another's question.
        Cache::flush();
    }

    /** @param array<string, mixed> $overrides */
    private function hubOffers(array $overrides = []): void
    {
        Http::fake(['hub.test/api/v1/catalogue' => Http::response([
            'products' => [$overrides + [
                'slug' => 'astralab-cms',
                'name' => 'Astra Lab CMS',
                'summary' => 'Self-hosted e-commerce.',
                'price' => 105000,
                'compare_price' => null,
                'discount' => 0,
                'seats' => 1,
            ]],
            'payment_methods' => [],
        ])]);
    }

    public function test_the_products_are_in_the_page(): void
    {
        $this->hubOffers();

        $this->get('/')
            ->assertOk()
            ->assertSee('Astra Lab CMS')
            ->assertSee('৳1,050', false)
            ->assertSee(route('product', 'astralab-cms'))
            // The apology belongs to a real outage and nothing else.
            ->assertDontSee('not answering');
    }

    public function test_a_sale_price_shows_what_it_was(): void
    {
        $this->hubOffers(['price' => 105000, 'compare_price' => 150000, 'discount' => 30]);

        $this->get('/')
            ->assertSee('৳1,050', false)
            ->assertSee('৳1,500', false)
            ->assertSee('30% off');
    }

    /**
     * The Products section and the Pricing card are a screen apart on one page,
     * and for a while they quoted different figures for the same product —
     * ৳4,500 in one and a typed-in ৳1,050 in the other. Both come from the
     * catalogue now, so they cannot drift again.
     */
    public function test_the_pricing_card_quotes_the_same_price_as_the_catalogue(): void
    {
        $this->hubOffers(['price' => 450000]);

        $page = $this->get('/')->assertOk()->getContent();

        $this->assertSame(2, substr_count($page, '৳4,500'), 'Both places should quote ৳4,500.');
        $this->assertStringNotContainsString('৳1,050', $page);
    }

    /**
     * The hub being unreachable is a bad afternoon for us and must not be a
     * broken page for a visitor — but it must not be silent either, or we are
     * back where we started.
     */
    public function test_the_page_still_renders_when_the_hub_is_down(): void
    {
        Http::fake(['hub.test/api/v1/catalogue' => Http::response('', 500)]);

        $this->get('/')
            ->assertOk()
            ->assertSee('not answering')
            ->assertSee('Own your online store');
    }

    /**
     * The bug in one line: the page must not depend on the browser fetching
     * anything to know what is for sale.
     *
     * Asserted against fetch itself rather than against the old address. The
     * address is named in a comment a few lines up in that file, so matching on
     * it passes for the wrong reason — and the next wrong URL would be a
     * different string anyway. What actually went wrong was the shape: a second
     * place asking the hub, over a connection a browser is entitled to refuse.
     */
    public function test_nothing_fetches_the_catalogue_from_the_browser(): void
    {
        $this->assertStringNotContainsString(
            'fetch(',
            file_get_contents(public_path('assets/site.js')),
        );
    }
}
