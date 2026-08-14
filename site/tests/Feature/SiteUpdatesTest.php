<?php

namespace Tests\Feature;

use App\Models\UpdateEntry;
use App\Models\User;
use App\Support\SelfUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

/**
 * Shipping a bug fix to this site without a shell.
 *
 * There is no git pull on this hosting. A fix arrives as replaced files, and
 * replaced files bring migrations — so if this path is broken, an update looks
 * like it worked and then fails on whichever page first touches a new column.
 */
class SiteUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = sys_get_temp_dir().'/site-update-'.getmypid();
        @mkdir($this->scratch, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratch.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->scratch);

        parent::tearDown();
    }

    private function anOperator(): User
    {
        return User::create([
            'name' => 'Nazmul',
            'email' => 'owner@test.local',
            'password' => Hash::make('a-long-enough-passphrase'),
        ]);
    }

    /** @param array<string, string> $entries */
    private function archive(string $name, array $entries): string
    {
        $path = $this->scratch.'/'.$name;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return $path;
    }

    public function test_the_updates_screen_opens(): void
    {
        $this->actingAs($this->anOperator())
            ->get('/apt-admin/updates')
            ->assertOk()
            ->assertSee('Install a new build')
            ->assertSee('What has been done');
    }

    public function test_it_is_behind_the_sign_in(): void
    {
        // This screen replaces the code the site runs on.
        $this->get('/apt-admin/updates')->assertRedirect();
        $this->post('/apt-admin/uploads/begin', ['filename' => 'x.zip'])->assertRedirect();
    }

    public function test_the_hub_build_is_refused_and_sent_to_the_right_place(): void
    {
        // The two archives sit beside each other in a Downloads folder and are
        // one word apart. The wrong one here would replace this site with the
        // hub — so the refusal has to say where it does belong.
        $hub = $this->archive('astralab-manage-1.0.0.zip', [
            'manage-app/artisan' => '<?php',
            'public_html/index.php' => '<?php',
        ]);

        $result = SelfUpdate::inspect($hub);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('manage.astrallabs.uk', $result['message']);
    }

    public function test_a_refusal_says_what_actually_arrived(): void
    {
        // Otherwise the wrong file and a good file that arrived damaged look
        // identical, and they need opposite actions.
        $result = SelfUpdate::inspect($this->archive('odd.zip', ['notes.txt' => 'hello']));

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('notes.txt', $result['message']);
    }

    public function test_applying_database_changes_is_written_down(): void
    {
        // Asked in the past tense, weeks later, by somebody looking at
        // behaviour nobody expected. File dates cannot answer it — updating
        // rewrites them by definition.
        $this->actingAs($this->anOperator())->post('/apt-admin/updates');

        $entry = UpdateEntry::first();

        $this->assertSame('database', $entry->kind);
        $this->assertSame('Nazmul', $entry->actor);
    }

    public function test_the_log_appears_on_the_screen(): void
    {
        UpdateEntry::record(UpdateEntry::BUILD, 'Version 1.0.1 installed — 142 files replaced', 'update build');

        $this->actingAs($this->anOperator())
            ->get('/apt-admin/updates')
            ->assertSee('Version 1.0.1 installed');
    }
}
