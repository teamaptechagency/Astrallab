<?php

namespace Tests\Feature;

use App\Support\Installer;
use Tests\TestCase;

/**
 * Running where nothing can be written.
 *
 * The site decides whether to show the shop or the installer by looking for a
 * file the installer wrote. On a read-only host nothing can write that file, so
 * without a way to say "there is nothing to install" every visitor is redirected
 * into a wizard that cannot finish — the whole shop, one long redirect loop.
 *
 * See DEPLOY-VERCEL.md.
 */
class ReadOnlyHostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nowhere, so the lock file cannot be found and only the flag can
        // decide. Without this the local copy's own lock would answer for it
        // and these would pass while proving nothing.
        config(['astralab.install_lock' => storage_path('framework/testing/no-lock-'.uniqid().'.json')]);
    }

    public function test_without_the_flag_a_site_with_no_lock_is_not_installed(): void
    {
        config(['astralab.installed' => false]);

        $this->assertFalse(Installer::isInstalled());
        $this->get('/')->assertRedirect('/install');
    }

    public function test_the_flag_says_there_is_nothing_to_install(): void
    {
        config(['astralab.installed' => true]);

        $this->assertTrue(Installer::isInstalled());
        $this->get('/')->assertOk();
    }

    /**
     * Off unless asked for. A site that wrongly believes it is installed shows
     * a shop with no settings behind it, which is harder to work out than being
     * offered a wizard you did not need.
     */
    public function test_it_is_off_by_default(): void
    {
        $this->assertFalse(
            filter_var(env('ASTRALAB_INSTALLED', false), FILTER_VALIDATE_BOOL),
        );
    }
}
