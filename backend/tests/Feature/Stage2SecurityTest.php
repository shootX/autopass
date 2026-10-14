<?php

use App\Mail\VerificationMail;
use App\Models\CorporateClient;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserVoucher;
use App\Support\Phone;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Mail::fake();
});

function s2Reset(): void
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function s2Corporate(array $extra = []): CorporateClient
{
    return CorporateClient::forceCreate($extra + [
        'name' => 'Corp', 'api_token' => Str::random(40), 'username' => 'corp'.Str::random(4),
        'password' => Hash::make('oldpass12'), 'credentials_custom' => true,
    ]);
}

function s2Partner(array $extra = []): Partner
{
    $id = DB::table('partners')->insertGetId($extra + [
        'login' => 'p'.Str::random(6), 'password' => Hash::make('partnerpw1'), 'name' => 'P', 'description' => 'd',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return Partner::find($id);
}

// 1. Profile edit never changes phone or email
it('ignores phone and email on profile edit', function () {
    $u = makeUser('555800001');
    $u->forceFill(['email' => 'old@example.com'])->save();
    $t = JWTAuth::fromUser($u);
    $this->withToken($t)->postJson('/api/me/edit', ['name' => 'New', 'phone' => '555800099', 'email' => 'evil@example.com'])->assertStatus(422);
    // Unchanged phone/email in the form are fine; the name is saved.
    $this->withToken($t)->postJson('/api/me/edit', ['name' => 'New', 'phone' => '+995 555 80 00 01', 'email' => 'old@example.com'])->assertOk();
    expect($u->fresh()->name)->toBe('New');
    $fresh = $u->fresh();
    expect(Phone::normalize($fresh->phone))->toBe('555 80 00 01')->and($fresh->email)->toBe('old@example.com');
});

it('does not grant test payment by moving the phone into TEST_PAYMENT_PHONES via profile edit', function () {
    config(['security.test_payment_phones' => ['555800050']]);
    $u = makeUser('555800002');
    $t = JWTAuth::fromUser($u);
    $this->withToken($t)->postJson('/api/me/edit', ['phone' => '555800050']);
    s2Reset();
    $this->withToken($t)->getJson('/api/testpay?amount=1')->assertForbidden();
});

it('keeps the email unchanged until the new address is verified', function () {
    $u = makeUser('555800003');
    $u->forceFill(['email' => 'old@example.com', 'email_verified_at' => now()])->save();
    $t = JWTAuth::fromUser($u);
    $this->withToken($t)->postJson('/api/email/set', ['email' => 'new@example.com'])->assertOk();
    $fresh = $u->fresh();
    expect($fresh->email)->toBe('old@example.com')->and($fresh->email_verified_at)->not->toBeNull()
        ->and($fresh->pending_email)->toBe('new@example.com');

    $code = null;
    Mail::assertSent(VerificationMail::class, function ($m) use (&$code) {
        $code = $m->code;

        return $m->hasTo('new@example.com');
    });
    $this->withToken($t)->postJson('/api/email/verify', ['code' => $code])->assertOk();
    $fresh = $u->fresh();
    expect($fresh->email)->toBe('new@example.com')->and($fresh->pending_email)->toBeNull()->and($fresh->email_verified_at)->not->toBeNull();
});

// 2. Ban
it('kills an existing API token and rejects login after a ban', function () {
    $u = makeUser('555800010');
    $u->password = 'secret12';
    $u->save();
    $t = JWTAuth::fromUser($u->fresh());
    $this->withToken($t)->getJson('/api/me')->assertOk();
    $admin = makeUser('555800011', User::ROLE_ADMIN);
    $this->actingAs($admin)->post("/dashboard/clients/{$u->id}/ban")->assertRedirect();
    s2Reset();
    $this->withToken($t)->getJson('/api/me')->assertUnauthorized();
    $this->postJson('/api/login', ['phone' => '555800010', 'password' => 'secret12'])->assertForbidden();
});

it('kills a banned admin web session and rejects web login', function () {
    $admin = makeUser('555800012', User::ROLE_ADMIN);
    $admin->forceFill(['email' => 'adm@example.com', 'password' => 'adminpass1'])->save();
    $this->actingAs($admin)->get('/dashboard')->assertOk();
    $admin->forceFill(['ban' => true])->save();
    $this->actingAs($admin->fresh())->get('/dashboard')->assertRedirect(route('login'));
    auth()->logout();
    $this->post('/login', ['email' => 'adm@example.com', 'password' => 'adminpass1']);
    expect(auth()->check())->toBeFalse();
});

it('kills a banned partner token and rejects partner login', function () {
    $p = s2Partner();
    $t = JWTAuth::fromUser($p);
    $p->forceFill(['ban' => true])->save();
    $this->withToken($t)->postJson('/api/partner/voucher/check', ['code' => 'ABCDEFGH'])->assertUnauthorized();
    $this->postJson('/api/partner/login', ['login' => $p->login, 'password' => 'partnerpw1'])->assertForbidden();
});

it('kills a banned company session and rejects company login', function () {
    $c = s2Corporate();
    $s = ['corporate_client_id' => $c->id, 'corporate_session_version' => 0];
    $this->withSession($s)->get('/partner/account')->assertOk();
    $c->forceFill(['ban' => true])->save();
    $this->withSession($s)->get('/partner/account')->assertRedirect(route('partner.login'));
    $this->flushSession();
    $this->post('/partner/login', ['username' => $c->username, 'password' => 'oldpass12'])->assertSessionHasErrors('username');
});

// 3. Corporate password
it('allows the first-login password endpoint only when a change is required', function () {
    $c = s2Corporate();
    $s = ['corporate_client_id' => $c->id, 'corporate_session_version' => 0];
    $this->withSession($s)->post('/partner/password', ['password' => 'hijacked1', 'password_confirmation' => 'hijacked1']);
    expect(Hash::check('oldpass12', $c->fresh()->password))->toBeTrue();
});

it('requires the current password for a normal change and revokes other sessions', function () {
    $c = s2Corporate();
    $s = ['corporate_client_id' => $c->id, 'corporate_session_version' => 0];
    $this->withSession($s)->post('/partner/account', ['username' => $c->username, 'password' => 'newpass12', 'password_confirmation' => 'newpass12'])
        ->assertSessionHasErrors('current_password');
    $this->withSession($s)->post('/partner/account', ['username' => $c->username, 'current_password' => 'oldpass12', 'password' => 'newpass12', 'password_confirmation' => 'newpass12'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('newpass12', $c->fresh()->password))->toBeTrue();
    $this->flushSession();
    // Another browser still holding the old session version is logged out.
    $this->withSession($s)->get('/partner/account')->assertRedirect(route('partner.login'));
});

// 4. Points + voucher atomic
function s2Voucher(int $price): int
{
    DB::statement('PRAGMA defer_foreign_keys = ON');
    $cat = DB::table('voucher_categories')->insertGetId(['name' => 'c', 'created_at' => now(), 'updated_at' => now()]);

    return DB::table('vouchers')->insertGetId(['name' => 'v', 'description' => 'd', 'photo' => 'p', 'category_id' => $cat, 'price' => $price, 'created_at' => now(), 'updated_at' => now()]);
}

it('refuses a voucher on insufficient balance', function () {
    $u = makeUser('555800020');
    $u->forceFill(['points' => 5])->save();
    $v = s2Voucher(10);
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/vouchers/buy', ['voucher_id' => $v])->assertJson(['success' => false]);
    expect((int) $u->fresh()->points)->toBe(5)->and(UserVoucher::count())->toBe(0)->and(Transaction::count())->toBe(0);
});

it('rolls back the deduction when voucher creation fails', function () {
    $u = makeUser('555800021');
    $u->forceFill(['points' => 50])->save();
    $v = s2Voucher(10);
    Transaction::creating(fn () => throw new RuntimeException('forced'));
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/vouchers/buy', ['voucher_id' => $v])->assertJson(['success' => false]);
    expect((int) $u->fresh()->points)->toBe(50)->and(UserVoucher::count())->toBe(0);
});

it('deducts once and creates one voucher on success', function () {
    $u = makeUser('555800022');
    $u->forceFill(['points' => 15])->save();
    $v = s2Voucher(10);
    $t = JWTAuth::fromUser($u);
    $this->withToken($t)->postJson('/api/vouchers/buy', ['voucher_id' => $v])->assertJson(['success' => true, 'points' => 5]);
    $this->withToken($t)->postJson('/api/vouchers/buy', ['voucher_id' => $v])->assertJson(['success' => false]);
    expect((int) $u->fresh()->points)->toBe(5)->and(UserVoucher::count())->toBe(1)->and(Transaction::count())->toBe(1);
});
