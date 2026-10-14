<?php

namespace Tests\Integration;

use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\TbcPayment;
use App\Models\User;
use App\Models\UserCar;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', DB::getDriverName());
        $database = (string) config('database.connections.pgsql.database');
        $port = (int) config('database.connections.pgsql.port');
        $localWasm = getenv('AUTOPASS_PGLITE_TEST') === '1' && $database === 'postgres' && $port >= 15400 && $port < 15500;
        $this->assertTrue(str_ends_with($database, '_test') || $localWasm, 'An isolated test database is required');
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    private function fixture(): array
    {
        $user = User::forceCreate(['phone' => '555900001', 'password' => 'review-only-password', 'role' => 0, 'phone_verified_at' => now(), 'ref_code' => 'pg001']);
        $manager = User::forceCreate(['phone' => '555900002', 'password' => 'review-only-password', 'role' => 1, 'phone_verified_at' => now(), 'ref_code' => 'pg002']);
        $wash = CarWash::create(['name' => 'Review branch', 'address' => 'Review', 'location' => 'Review', 'manager_id' => $manager->id, 'work_time_start' => '09:00', 'work_time_end' => '20:00']);
        $brand = CarBrand::create(['name' => 'Review']);
        $model = CarModel::create(['brand_id' => $brand->id, 'name' => 'Review', 'type' => 'sedan']);
        $car = UserCar::create(['user_id' => $user->id, 'model_id' => $model->id, 'plate' => 'AA123BB']);
        $service = CarWashService::forceCreate(['name' => 'Review']);
        $wash->services()->attach($service->id);

        return compact('user', 'wash', 'car', 'service');
    }

    private function race(array $payload): array
    {
        $jobs = [];
        for ($i = 0; $i < 2; $i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, base_path('tests/Support/Stage3ConcurrentWorker.php')],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $this->assertIsResource($process);
            $jobs[] = [$process, $pipes];
        }
        foreach ($jobs as [$process, $pipes]) {
            fwrite($pipes[0], json_encode($payload, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
        }
        $results = [];
        foreach ($jobs as [$process, $pipes]) {
            stream_set_timeout($pipes[1], 30);
            stream_set_timeout($pipes[2], 30);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $error);
            $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }

    public function test_parallel_bookings_do_not_claim_the_same_slot(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $f = $this->fixture();
        $results = $this->race(['mode' => 'booking', 'user_id' => $f['user']->id, 'data' => [
            'car_wash_id' => $f['wash']->id, 'car_id' => $f['car']->id, 'services' => [$f['service']->id],
            'date' => now()->addDay()->toDateString(), 'time' => '10:00',
        ]]);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['ok'])), json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertSame(1, Appointment::count());
    }

    public function test_parallel_checkout_requests_share_one_order(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $f = $this->fixture();
        $package = Package::create(['car_type' => 'sedan', 'count_washes' => 5]);
        PackagePrice::create(['package_id' => $package->id, 'month' => 1, 'price' => 20]);
        $results = $this->race(['mode' => 'package', 'user_id' => $f['user']->id, 'data' => [
            'car_id' => $f['car']->id, 'number_of_washes' => 5, 'sub_term' => 1, 'renewal' => false,
        ]]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['url'], $results[1]['url']);
        $this->assertSame(1, TbcPayment::count());
    }
}
