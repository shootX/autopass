<?php

use App\Models\CorporateClient;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserTicket;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

// Mass assignment: privileged columns are never fillable.
it('does not mass-assign privileged user columns', function () {
    $u = (new User)->fill([
        'name' => 'x', 'role' => User::ROLE_ADMIN, 'phone_verified_at' => now(), 'referral_id' => 7,
        'ref_code' => 'HACK1', 'points' => 1000, 'ban' => false, 'token_version' => 9, 'pending_email' => 'a@b.c',
    ]);
    expect($u->name)->toBe('x')
        ->and($u->role)->toBeNull()->and($u->phone_verified_at)->toBeNull()->and($u->referral_id)->toBeNull()
        ->and($u->ref_code)->toBeNull()->and($u->points)->toBeNull()->and($u->token_version)->toBeNull()
        ->and($u->pending_email)->toBeNull();
});

it('does not mass-assign partner security columns', function () {
    $p = (new Partner)->fill(['name' => 'n', 'ban' => true, 'password_must_change' => false, 'token_version' => 9, 'temp_password_expires_at' => now(), 'password' => 'x']);
    expect($p->name)->toBe('n')->and($p->ban)->toBeNull()->and($p->password_must_change)->toBeNull()
        ->and($p->token_version)->toBeNull()->and($p->temp_password_expires_at)->toBeNull()->and($p->password)->toBeNull();
});

it('does not mass-assign company credential columns', function () {
    $c = (new CorporateClient)->fill([
        'name' => 'n', 'password' => 'x', 'api_token' => 'x', 'password_must_change' => false, 'session_version' => 9,
        'credentials_custom' => true, 'temp_password_expires_at' => now(), 'credentials_retired_at' => now(), 'ban' => true, 'username' => 'u',
    ]);
    expect($c->name)->toBe('n')->and($c->password)->toBeNull()->and($c->api_token)->toBeNull()
        ->and($c->password_must_change)->toBeNull()->and($c->session_version)->toBeNull()->and($c->credentials_custom)->toBeNull()
        ->and($c->temp_password_expires_at)->toBeNull()->and($c->credentials_retired_at)->toBeNull()->and($c->ban)->toBeNull()
        ->and($c->username)->toBeNull();
});

it('only saves the known fields on admin car wash edit', function () {
    $admin = makeUser('555810001', User::ROLE_ADMIN);
    $mgr = makeUser('555810002', User::ROLE_MANAGER);
    $id = DB::table('car_washes')->insertGetId(['name' => 'W', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'x', 'manager_id' => $mgr->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($admin)->post("/dashboard/washings/{$id}/edit_save", [
        'name' => 'W2', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'x',
        'id' => 999, 'created_at' => '2000-01-01 00:00:00',
    ]);
    $row = DB::table('car_washes')->find($id);
    expect($row)->not->toBeNull()->and($row->name)->toBe('W2')->and(substr((string) $row->created_at, 0, 4))->not->toBe('2000');

    // A non-manager can't be made the branch manager.
    $plain = makeUser('555810003');
    $this->actingAs($admin)->post("/dashboard/washings/{$id}/edit_save", [
        'name' => 'W3', 'address' => 'a', 'work_time_start' => '09:00', 'work_time_end' => '20:00', 'location' => 'x', 'manager_id' => $plain->id,
    ])->assertSessionHasErrors('manager_id');
    expect((int) DB::table('car_washes')->find($id)->manager_id)->toBe($mgr->id);
});

// Tickets: atomic points deduction
function s2Ticket(int $price, int $count = 0): int
{
    return DB::table('tickets')->insertGetId(['name' => 't', 'description' => 'd', 'photo' => 'p', 'date_to' => now()->addMonth()->toDateString(), 'price' => $price, 'count' => $count, 'created_at' => now(), 'updated_at' => now()]);
}

it('refuses tickets on insufficient balance and fractional qty', function () {
    $u = makeUser('555810010');
    $u->forceFill(['points' => 5])->save();
    $t = s2Ticket(3);
    $tok = JWTAuth::fromUser($u);
    $this->withToken($tok)->postJson('/api/tickets/buy', ['ticket_id' => $t, 'qty' => 2])->assertJson(['success' => false]);
    $this->withToken($tok)->postJson('/api/tickets/buy', ['ticket_id' => $t, 'qty' => 1.5])->assertJson(['success' => false]);
    expect((int) $u->fresh()->points)->toBe(5)->and(UserTicket::count())->toBe(0);
});

it('respects the ticket limit including qty', function () {
    $u = makeUser('555810011');
    $u->forceFill(['points' => 100])->save();
    $t = s2Ticket(1, 2);
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/tickets/buy', ['ticket_id' => $t, 'qty' => 3])->assertJson(['success' => false]);
    expect(UserTicket::count())->toBe(0)->and((int) $u->fresh()->points)->toBe(100);
});

it('rolls back tickets and points when the ledger write fails', function () {
    $u = makeUser('555810012');
    $u->forceFill(['points' => 10])->save();
    $t = s2Ticket(2);
    Transaction::creating(fn () => throw new RuntimeException('forced'));
    $this->withToken(JWTAuth::fromUser($u))->postJson('/api/tickets/buy', ['ticket_id' => $t, 'qty' => 2])->assertJson(['success' => false]);
    expect((int) $u->fresh()->points)->toBe(10)->and(UserTicket::count())->toBe(0);
});
