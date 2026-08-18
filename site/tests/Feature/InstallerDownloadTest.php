<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Getting the installer to somebody who has paid.
 *
 * The order page has told every customer to "download the installer" since the
 * day it was written, and there was no link, no route and nothing to download.
 * These exist so that cannot be true again.
 */
class InstallerDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['astralab.hub_url' => 'https://hub.test']);

        Cache::flush();
    }

    private function hubHasProduct(): void
    {
        Http::fake([
            'hub.test/api/v1/catalogue' => Http::response([
                'products' => [[
                    'slug' => 'astralab-cms',
                    'name' => 'Astra Lab CMS',
                    'summary' => 'Self-hosted e-commerce.',
                    'price' => 105000,
                    'compare_price' => null,
                    'discount' => 0,
                    'seats' => 1,
                ]],
                'payment_methods' => [],
            ]),
            'hub.test/api/v1/installer/astralab-cms' => Http::response(
                "<?php\nconst PRODUCT_SLUG = 'astralab-cms';\n",
            ),
        ]);
    }

    public function test_it_serves_the_installer_as_a_download(): void
    {
        $this->hubHasProduct();

        $response = $this->get('/installer/astralab-cms');

        $response->assertOk();
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="astralab-cms-installer.php"',
        );
        $this->assertStringContainsString("const PRODUCT_SLUG = 'astralab-cms';", $response->getContent());
    }

    /**
     * A .php file must never be served in a way that invites anything to run or
     * render it. text/plain and an attachment disposition are the whole of that.
     */
    public function test_it_is_never_served_as_something_to_render(): void
    {
        $this->hubHasProduct();

        $response = $this->get('/installer/astralab-cms');

        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        // Laravel adds "private" of its own accord; what matters is no-store,
        // because this is built from whatever release is current.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_an_unknown_product_is_a_404(): void
    {
        $this->hubHasProduct();

        $this->get('/installer/no-such-thing')->assertNotFound();
    }

    /**
     * Nothing published yet. The hub's own sentence is the useful one, so it is
     * passed through rather than replaced with something vaguer.
     */
    public function test_it_passes_on_what_the_hub_says_when_there_is_nothing_to_send(): void
    {
        Http::fake([
            'hub.test/api/v1/catalogue' => Http::response([
                'products' => [['slug' => 'astralab-cms', 'name' => 'Astra Lab CMS', 'price' => 105000,
                    'compare_price' => null, 'discount' => 0, 'seats' => 1, 'summary' => '']],
                'payment_methods' => [],
            ]),
            'hub.test/api/v1/installer/astralab-cms' => Http::response(
                ['ok' => false, 'message' => 'There is no published release for Astra Lab CMS yet.'],
                409,
            ),
        ]);

        $this->from('/order/AL-1234')
            ->get('/installer/astralab-cms')
            ->assertRedirect('/order/AL-1234')
            ->assertSessionHas('problem', 'There is no published release for Astra Lab CMS yet.');
    }

    /** The hub being unreachable is our bad afternoon, not a stack trace for a buyer. */
    public function test_an_unreachable_hub_says_so_kindly(): void
    {
        Http::fake([
            'hub.test/api/v1/catalogue' => Http::response([
                'products' => [['slug' => 'astralab-cms', 'name' => 'Astra Lab CMS', 'price' => 105000,
                    'compare_price' => null, 'discount' => 0, 'seats' => 1, 'summary' => '']],
                'payment_methods' => [],
            ]),
            'hub.test/api/v1/installer/astralab-cms' => fn () => throw new \RuntimeException('connection refused'),
        ]);

        $this->from('/order/AL-1234')
            ->get('/installer/astralab-cms')
            ->assertRedirect('/order/AL-1234');

        $this->assertStringContainsString('your licence key is safe', session('problem'));
    }
}
