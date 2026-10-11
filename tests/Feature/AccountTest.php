<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_validation_and_password_hashing(): void
    {
        $this->post('/register', ['name' => '', 'email' => 'invalid', 'password' => 'short'])->assertSessionHasErrors();
        $this->post('/register', ['name' => 'Demo Trader', 'email' => 'trader@example.test', 'password' => 'DemoStrong!123', 'password_confirmation' => 'DemoStrong!123'])->assertSessionHasNoErrors();
        $u = User::where('email', 'trader@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('DemoStrong!123', $u->password));
        $this->assertNotNull($u->referral_code);
        $this->assertDatabaseCount('wallets', 1);
    }

    public function test_login_logout(): void
    {
        $u = User::factory()->create();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_unverified_customer_is_redirected_to_verification(): void
    {
        $u = User::factory()->unverified()->create();
        $this->actingAs($u)->get('/trade')->assertRedirect('/email/verify');
    }

    public function test_support_isolation(): void
    {
        $u = User::factory()->create();
        $v = User::factory()->create();
        $this->actingAs($u)->post('/support', ['body' => 'Private question'])->assertSessionHasNoErrors();
        $this->actingAs($v)->get('/support')->assertInertia(fn ($p) => $p->has('messages', 0));
    }

    public function test_kyc_files_are_private_and_other_users_cannot_download(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $v = User::factory()->create();
        // Real 1px PNG content, independent of the optional GD extension.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1FoAAAAASUVORK5CYII=');
        $this->actingAs($u)->post('/kyc', ['full_name' => 'Sample Person', 'national_id' => 'DEMO-ID', 'consent' => true, 'front' => UploadedFile::fake()->createWithContent('front.png', $png), 'back' => UploadedFile::fake()->createWithContent('back.png', $png)])->assertSessionHasNoErrors();
        $doc = DB::table('kyc_documents')->first();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringNotContainsString('DEMO-ID', DB::table('kyc_applications')->value('national_id'));
        $this->actingAs($v)->get('/kyc/document/'.$doc->id)->assertForbidden();
    }

    public function test_kyc_rejects_executable_upload(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/kyc', ['full_name' => 'Sample', 'national_id' => 'ID', 'consent' => true, 'front' => UploadedFile::fake()->create('bad.php', 10), 'back' => UploadedFile::fake()->create('bad.php', 10)])->assertSessionHasErrors(['front', 'back']);
    }

    public function test_password_reset_request_uses_laravel_notification(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($u, ResetPassword::class);
    }

    public function test_mfa_enrollment_requires_password_confirmation(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/user/two-factor-authentication')->assertStatus(423);
        $this->post('/user/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors();
        $this->postJson('/user/two-factor-authentication')->assertSuccessful();
        $this->assertNotNull($u->fresh()->two_factor_secret);
    }

    public function test_customer_has_no_admin_access(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/admin')->assertForbidden();
    }
}
