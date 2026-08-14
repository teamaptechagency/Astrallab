<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Configuring the link to the hub from a screen, not from .env.
 *
 * .env means a file manager, and a file manager means "saved as .env.txt" and
 * an afternoon. These two settings are the whole connection: where to ask, and
 * what to say when asking.
 */
class HubSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function anOperator(): User
    {
        return User::create([
            'name' => 'Nazmul',
            'email' => 'owner@test.local',
            'password' => Hash::make('a-long-enough-passphrase'),
        ]);
    }

    public function test_the_hub_settings_are_on_the_screen(): void
    {
        $this->actingAs($this->anOperator())
            ->get('/apt-admin/settings')
            ->assertOk()
            ->assertSee('Hub address')
            ->assertSee('Store secret')
            ->assertSee('Test the connection');
    }

    public function test_the_secret_is_never_rendered_back_into_the_page(): void
    {
        // A secret on a screen is a secret in a screenshot, in a browser cache,
        // and over whoever is standing behind you.
        Setting::putMany(['store_secret' => 'the-real-shared-secret']);

        $this->actingAs($this->anOperator())
            ->get('/apt-admin/settings')
            ->assertOk()
            ->assertDontSee('the-real-shared-secret')
            // But it does say whether one is set, which is the question
            // somebody actually has.
            ->assertSee('Set — leave empty to keep it', false);
    }

    public function test_saving_with_the_secret_box_empty_keeps_the_secret(): void
    {
        // The box always arrives empty, because the value is never rendered
        // back. Without this, editing a phone number would wipe the one thing
        // nobody can retype from memory — and the failure would surface as
        // paying customers receiving nothing.
        Setting::putMany(['store_secret' => 'the-real-shared-secret']);

        $this->actingAs($this->anOperator())
            ->post('/apt-admin/settings', [
                'hub_url' => 'https://manage.astrallabs.uk',
                'store_secret' => '',
                'contact_email' => 'hello@astrallabs.uk',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('the-real-shared-secret', Setting::get('store_secret'));
        $this->assertSame('https://manage.astrallabs.uk', Setting::get('hub_url'));
    }

    public function test_a_new_secret_replaces_the_old_one(): void
    {
        Setting::putMany(['store_secret' => 'the-old-one']);

        $this->actingAs($this->anOperator())
            ->post('/apt-admin/settings', ['store_secret' => 'the-new-one'])
            ->assertSessionHasNoErrors();

        $this->assertSame('the-new-one', Setting::get('store_secret'));
    }

    public function test_what_is_saved_is_what_the_shop_uses(): void
    {
        // The point of settings living in the database: nothing else in the
        // application knows they exist. It reads config, and the saved value
        // has replaced what .env put there.
        Setting::putMany(['hub_url' => 'https://hub.example.test']);

        Settings::apply();

        $this->assertSame('https://hub.example.test', config('astralab.hub_url'));
    }

    public function test_the_test_button_says_when_there_is_no_address(): void
    {
        config(['astralab.hub_url' => '']);

        $this->actingAs($this->anOperator())
            ->post('/apt-admin/settings/test')
            ->assertSessionHas('hub', fn ($hub) => $hub['ok'] === false
                && str_contains($hub['headline'], 'No hub address'));
    }

    public function test_the_test_button_separates_unreachable_from_refused(): void
    {
        // "It does not work" sends somebody checking the wrong one of the two.
        config([
            'astralab.hub_url' => 'http://127.0.0.1:9',
            'astralab.store_secret' => 'something',
        ]);

        $this->actingAs($this->anOperator())
            ->post('/apt-admin/settings/test')
            ->assertSessionHas('hub', fn ($hub) => $hub['ok'] === false
                && str_contains($hub['headline'], 'did not answer'));
    }
}
