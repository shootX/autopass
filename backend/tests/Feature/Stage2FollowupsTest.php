<?php

use App\Models\CorporateClient;
use App\Models\Partner;
use App\Models\ReferralCodeTemp;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

function fuResetJwt(): void
{
    foreach (['tymon.jwt', 'tymon.jwt.auth'] as $id) {
        app()->forgetInstance($id);
    }
    JWTAuth::clearResolvedInstances();
    app('auth')->forgetGuards();
}

function fuAdmin(): User
{
    return User::query()->where('role', User::ROLE_ADMIN)->first() ?? makeUser('555700001', User::ROLE_ADMIN);
}

// (a) referral hash
it('validates save-hash strictly', function () {
    $owner = makeUser('555700010');
    $hash = str_repeat('a', 64);

    $this->postJson('/api/ref/save-hash', ['hash' => 'x', 'code' => $owner->ref_code])->assertStatus(422);
    $this->postJson('/api/ref/save-hash', ['hash' => $hash, 'code' => 'zzzzz'])->assertStatus(422);
    $this->postJson('/api/ref/save-hash', ['hash' => $hash])->assertStatus(422);
    $this->postJson('/api/ref/save-hash', ['hash' => str_repeat('a', 5000), 'code' => $owner->ref_code])->assertStatus(422);
    expect(ReferralCodeTemp::count())->toBe(0);

    $this->postJson('/api/ref/save-hash', ['hash' => $hash, 'code' => $owner->ref_code])->assertOk();
    $this->postJson('/api/ref/check-hash', ['hash' => $hash])->assertOk()->assertJsonPath('code', $owner->ref_code);
});

it('throttles save-hash', function () {
    $owner = makeUser('555700011');
    $codes = [];
    for ($i = 0; $i < 12; $i++) {
        $codes[] = $this->postJson('/api/ref/save-hash', ['hash' => hash('sha256', (string) $i), 'code' => $owner->ref_code])->status();
    }
    expect($codes)->toContain(429);
});

// (b) partner ban
it('lets admin ban and unban a partner with immediate token revocation', function () {
    $id = DB::table('partners')->insertGetId([
        'login' => 'pban', 'password' => Hash::make('partnerpw1'), 'name' => 'P', 'description' => 'd',
        'password_must_change' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $token = $this->postJson('/api/partner/login', ['login' => 'pban', 'password' => 'partnerpw1'])->assertOk()->json('access_token');

    $this->actingAs(fuAdmin())->post('/dashboard/partners/'.$id.'/ban')->assertRedirect();
    expect((bool) Partner::find($id)->ban)->toBeTrue();
    auth()->logout();
    fuResetJwt();

    $this->withToken($token)->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertStatus(401);
    fuResetJwt();
    $this->postJson('/api/partner/login', ['login' => 'pban', 'password' => 'partnerpw1'])->assertForbidden();

    $this->actingAs(fuAdmin())->post('/dashboard/partners/'.$id.'/ban')->assertRedirect();
    expect((bool) Partner::find($id)->ban)->toBeFalse();
    auth()->logout();
    fuResetJwt();
    // Unban does not resurrect the old token.
    $this->withToken($token)->postJson('/api/partner/voucher/check', ['code' => 'AB12CD34'])->assertUnauthorized();
    fuResetJwt();
    $this->postJson('/api/partner/login', ['login' => 'pban', 'password' => 'partnerpw1'])->assertOk();
});

// (b) company ban
it('lets admin ban a company: web session, api key and login all rejected', function () {
    $c = CorporateClient::forceCreate([
        'name' => 'Corp', 'api_token' => Str::random(40), 'username' => 'corpban',
        'password' => Hash::make('oldpass12'), 'credentials_custom' => true, 'password_must_change' => false,
    ]);
    $this->getJson('/api/corporate/vehicles', ['X-Api-Key' => $c->api_token])->assertOk();

    $this->actingAs(fuAdmin())->post('/dashboard/corporate/'.$c->id.'/ban')->assertRedirect();
    auth()->logout();
    $c->refresh();
    expect((bool) $c->ban)->toBeTrue();

    $this->withSession(['corporate_client_id' => $c->id, 'corporate_session_version' => 0])
        ->get('/partner/')->assertRedirect(route('partner.login'));
    $this->getJson('/api/corporate/vehicles', ['X-Api-Key' => $c->api_token])->assertForbidden();
    $this->post('/partner/login', ['username' => 'corpban', 'password' => 'oldpass12'])->assertSessionHasErrors();

    $this->actingAs(fuAdmin())->post('/dashboard/corporate/'.$c->id.'/ban')->assertRedirect();
    auth()->logout();
    expect((bool) $c->fresh()->ban)->toBeFalse();
    $this->getJson('/api/corporate/vehicles', ['X-Api-Key' => $c->api_token])->assertOk();
});

it('shows ban actions in the admin lists', function () {
    $pid = DB::table('partners')->insertGetId(['login' => 'plist', 'password' => 'x', 'name' => 'P', 'description' => 'd', 'created_at' => now(), 'updated_at' => now()]);
    CorporateClient::forceCreate(['name' => 'C', 'api_token' => Str::random(40), 'username' => 'clist', 'password' => 'x']);
    $admin = fuAdmin();
    $this->actingAs($admin)->get('/dashboard/partners/'.$pid.'/edit')->assertOk()->assertSee('/ban', false);
    $this->actingAs($admin)->get('/dashboard/corporate')->assertOk()->assertSee('/ban', false);
});

// (c) admin edits phone/email
it('audits admin phone/email edits and revokes the user sessions', function () {
    $admin = fuAdmin();
    $u = makeUser('555700020');
    $u->forceFill(['email' => 'old@example.com', 'name' => 'N', 'surname' => 'S', 'sex' => 'male'])->save();
    $token = JWTAuth::fromUser($u);
    DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin)->post('/dashboard/clients/'.$u->id.'/edit_save', [
        'name' => 'N', 'surname' => 'S', 'email' => 'new@example.com', 'phone' => '555700099', 'sex' => 'male',
        'date_of_birth' => '1990-01-01',
    ])->assertRedirect();
    auth()->logout();

    $rows = DB::table('admin_audit_logs')->where('target_id', $u->id)->orderBy('field')->get();
    expect($rows)->toHaveCount(2);
    expect($rows[0]->field)->toBe('email')->and($rows[0]->admin_id)->toBe($admin->id)
        ->and($rows[0]->old_value)->not->toContain('old@')->and($rows[0]->new_value)->not->toContain('new@')
        ->and($rows[0]->created_at)->not->toBeNull();
    expect($rows[1]->field)->toBe('phone')->and($rows[1]->new_value)->not->toContain('700099');

    expect(DB::table('sessions')->where('user_id', $u->id)->count())->toBe(0);
    fuResetJwt();
    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});

it('does not audit or revoke when phone/email are unchanged', function () {
    $u = makeUser('555700021');
    $u->forceFill(['email' => 'same@example.com'])->save();
    $tv = (int) $u->fresh()->token_version;
    $this->actingAs(fuAdmin())->post('/dashboard/clients/'.$u->id.'/edit_save', [
        'name' => 'X', 'surname' => 'S', 'email' => 'same@example.com', 'phone' => $u->phone, 'sex' => 'male', 'date_of_birth' => '1990-01-01',
    ])->assertRedirect();
    expect(DB::table('admin_audit_logs')->count())->toBe(0)->and((int) $u->fresh()->token_version)->toBe($tv);
});

it('blocks non-admins from editing users', function () {
    $u = makeUser('555700022');
    $this->actingAs(makeUser('555700023', User::ROLE_MANAGER))
        ->post('/dashboard/clients/'.$u->id.'/edit_save', ['email' => 'x@example.com'])->assertForbidden();
});
