<?php

use App\Enums\AppointmentStatus;
use App\Jobs\SendPush;
use App\Models\Appointment;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\UserPushToken;
use App\Services\Push\ApnsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('does not send an old account push after that phone signs out and joins another account', function () {
    Http::preventStrayRequests();
    Queue::fake([SendPush::class]);
    $first = makeUser('555 97 01 01');
    $second = makeUser('555 97 01 02');
    $device = str_repeat('a7', 32);
    $firstSession = cwAs($first);
    $this->withToken($firstSession)->postJson('/api/device/apns', ['token' => $device, 'lang' => 'en'])->assertOk();

    $first->sendPush(['en' => 'Appointment confirmed'], ['en' => 'Your appointment for 05.11.2026, 10:00 is confirmed.']);
    $queued = null;
    Queue::assertPushed(SendPush::class, function (SendPush $job) use (&$queued) {
        $queued = unserialize(serialize($job));

        return true;
    });
    $this->withToken($firstSession)->postJson('/api/logout', ['apns_token' => $device])->assertOk();
    expect(UserPushToken::where('user_id', $first->id)->count())->toBe(0);
    $this->withToken(cwAs($second))->postJson('/api/device/apns', ['token' => $device, 'lang' => 'en'])->assertOk();

    $sent = [];
    $apns = Mockery::mock(ApnsClient::class);
    $apns->shouldReceive('send')->andReturnUsing(function ($token, $title, $body) use (&$sent) {
        $sent[] = compact('token', 'title', 'body');

        return ApnsClient::SENT;
    });
    app()->instance(ApnsClient::class, $apns);
    $queued->handle();

    expect($sent)->toBeEmpty();
});

it('enforces the one booking per car per week rule when the manager reschedules', function () {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
    $f = cwFleet();
    $manager = cwManager('555 97 02 01');
    $wash = CarWash::where('manager_id', $manager->id)->firstOrFail();
    $service = CarWashService::forceCreate(['name' => 'QA wash']);
    $wash->services()->attach($service->id);
    $body = ['car_wash_id' => $wash->id, 'car_id' => $f['userCar']->id, 'service_id' => $service->id, 'time' => '10:00'];
    $driverSession = cwAs($f['driver']);
    $first = $this->withToken($driverSession)->postJson('/api/appointments/add', $body + ['date' => '2026-11-05'])->assertOk();
    $second = $this->withToken($driverSession)->postJson('/api/appointments/add', $body + ['date' => '2026-11-12'])->assertOk();
    $blocked = $this->withToken($driverSession)->postJson('/api/appointments/add', array_replace($body, ['date' => '2026-11-06', 'time' => '11:00']));
    $blocked->assertStatus(422);
    $moved = $this->withToken(cwAs($manager))->postJson('/api/appointments/'.$second->json('appointment.id').'/edit', [
        'date' => '2026-11-06', 'time' => '11:00',
    ]);
    $active = Appointment::where('car_id', $f['userCar']->id)->whereIn('approved', AppointmentStatus::active())
        ->whereDate('date', '>=', '2026-11-02')->whereDate('date', '<=', '2026-11-08')->count();
    $moved->assertStatus(422);
    expect($active)->toBe(1);
    $this->withToken(cwAs($manager))->postJson('/api/appointments/'.$second->json('appointment.id').'/edit', [
        'date' => '2026-11-13', 'time' => '11:00',
    ])->assertOk();
});

it('delivers a queued push to its unchanged recipient and skips jobs without a snapshot', function () {
    Http::preventStrayRequests();
    $user = makeUser('555 97 03 01');
    $device = str_repeat('b8', 32);
    $this->withToken(cwAs($user))->postJson('/api/device/apns', ['token' => $device])->assertOk();
    $apns = Mockery::mock(ApnsClient::class);
    $apns->shouldReceive('send')->once()->andReturn(ApnsClient::SENT);
    app()->instance(ApnsClient::class, $apns);
    $job = unserialize(serialize(new SendPush([$device], ['ka' => 'Title'], ['ka' => 'Body'], [], $user->id)));
    $job->handle();
    // Old serialized payloads have no owner snapshot: fail closed after a worker restart.
    (new ReflectionProperty($job, 'recipients'))->setValue($job, null);
    $job->handle();
});
