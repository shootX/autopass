<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarWash;
use App\Models\CarWashService;
use App\Models\Contact;
use App\Models\Faq;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Partner;
use App\Models\Promo;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserCar;
use App\Models\UserPackage;
use App\Models\UserTicket;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\VoucherCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@gewash.ge'],
            [
                'name' => 'ადმინი',
                'surname' => 'გეოვოში',
                'phone' => '555000000',
                'password' => 'admin123',
                'role' => User::ROLE_ADMIN,
                'sex' => 'male',
                'ref_code' => 'ADMN1',
                'points' => 0,
            ],
        );

        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@gewash.ge'],
            [
                'name' => 'ნინო',
                'surname' => 'ბერიძე',
                'phone' => '555111222',
                'password' => 'manager123',
                'role' => User::ROLE_MANAGER,
                'sex' => 'female',
                'ref_code' => 'MNGR1',
            ],
        );

        $clients = [
            ['email' => 'giorgi@gewash.ge', 'name' => 'გიორგი', 'surname' => 'კაპანაძე', 'phone' => '555101010', 'sex' => 'male', 'ref_code' => 'CLNT1', 'points' => 240],
            ['email' => 'ana@gewash.ge', 'name' => 'ანა', 'surname' => 'მელაძე', 'phone' => '555202020', 'sex' => 'female', 'ref_code' => 'CLNT2', 'points' => 80],
            ['email' => 'luka@gewash.ge', 'name' => 'ლუკა', 'surname' => 'ჭელიძე', 'phone' => '555303030', 'sex' => 'male', 'ref_code' => 'CLNT3', 'points' => 15],
        ];

        $users = [];
        foreach ($clients as $client) {
            $users[] = User::query()->updateOrCreate(
                ['email' => $client['email']],
                $client + ['password' => 'client123', 'role' => User::ROLE_USER],
            );
        }

        $toyota = CarBrand::query()->updateOrCreate(['name' => 'Toyota'], []);
        $bmw = CarBrand::query()->updateOrCreate(['name' => 'BMW'], []);
        $mercedes = CarBrand::query()->updateOrCreate(['name' => 'Mercedes-Benz'], []);

        $camry = CarModel::query()->updateOrCreate(['brand_id' => $toyota->id, 'name' => 'Camry'], ['type' => 'sedan']);
        $rav4 = CarModel::query()->updateOrCreate(['brand_id' => $toyota->id, 'name' => 'RAV4'], ['type' => 'suv']);
        $x5 = CarModel::query()->updateOrCreate(['brand_id' => $bmw->id, 'name' => 'X5'], ['type' => 'suv']);
        $cClass = CarModel::query()->updateOrCreate(['brand_id' => $mercedes->id, 'name' => 'C-Class'], ['type' => 'sedan']);

        $cars = [
            UserCar::query()->updateOrCreate(['plate' => 'AA001AA'], ['user_id' => $users[0]->id, 'model_id' => $camry->id]),
            UserCar::query()->updateOrCreate(['plate' => 'BB002BB'], ['user_id' => $users[1]->id, 'model_id' => $rav4->id]),
            UserCar::query()->updateOrCreate(['plate' => 'CC003CC'], ['user_id' => $users[2]->id, 'model_id' => $x5->id]),
            UserCar::query()->updateOrCreate(['plate' => 'DD004DD'], ['user_id' => $users[0]->id, 'model_id' => $cClass->id]),
        ];

        $services = [];
        foreach (['გარე რეცხვა', 'სალონის წმენდა', 'ცვილი'] as $name) {
            $services[] = CarWashService::query()->firstOrCreate(['name' => $name]);
        }

        $washes = [
            CarWash::query()->updateOrCreate(
                ['name' => 'GeoWash ვაკე'],
                [
                    'address' => 'ჭავჭავაძის გამზირი 32, თბილისი',
                    'work_time_start' => '09:00:00',
                    'work_time_end' => '21:00:00',
                    'location' => '41.707,44.769',
                    'manager_id' => $manager->id,
                ],
            ),
            CarWash::query()->updateOrCreate(
                ['name' => 'GeoWash საბურთალო'],
                [
                    'address' => 'ვაჟა-ფშაველას გამზირი 45, თბილისი',
                    'work_time_start' => '08:00:00',
                    'work_time_end' => '22:00:00',
                    'location' => '41.725,44.752',
                    'manager_id' => $manager->id,
                ],
            ),
        ];

        foreach ($washes as $wash) {
            $wash->services()->sync(collect($services)->pluck('id'));
        }

        $sedan = Package::query()->updateOrCreate(['car_type' => 'sedan', 'count_washes' => 4], []);
        $suv = Package::query()->updateOrCreate(['car_type' => 'suv', 'count_washes' => 8], []);
        $sedanPrice = PackagePrice::query()->updateOrCreate(
            ['package_id' => $sedan->id, 'month' => 1],
            ['price' => 49.00],
        );
        PackagePrice::query()->updateOrCreate(
            ['package_id' => $suv->id, 'month' => 1],
            ['price' => 89.00],
        );

        UserPackage::query()->updateOrCreate(
            ['user_id' => $users[0]->id, 'user_car_id' => $cars[0]->id],
            [
                'package_id' => $sedan->id,
                'price_id' => $sedanPrice->id,
                'start_date' => now()->subDays(5),
                'end_date' => now()->addMonth(),
                'number_of_washes' => 4,
                'used_washes' => 1,
                'qr_code' => 'PKG000000001',
            ],
        );

        $appointments = [
            ['user' => $users[0], 'car' => $cars[0], 'wash' => $washes[0], 'date' => now()->addDay()->toDateString(), 'time' => '10:30:00', 'approved' => false, 'qr' => 'APT000000001'],
            ['user' => $users[1], 'car' => $cars[1], 'wash' => $washes[1], 'date' => now()->toDateString(), 'time' => '14:00:00', 'approved' => true, 'qr' => 'APT000000002'],
            ['user' => $users[2], 'car' => $cars[2], 'wash' => $washes[0], 'date' => now()->subDay()->toDateString(), 'time' => '18:15:00', 'approved' => true, 'qr' => 'APT000000003'],
        ];
        foreach ($appointments as $row) {
            $appointment = Appointment::query()->updateOrCreate(
                ['qr_code' => $row['qr']],
                [
                    'user_id' => $row['user']->id,
                    'car_id' => $row['car']->id,
                    'car_wash_id' => $row['wash']->id,
                    'date' => $row['date'],
                    'time' => $row['time'],
                    'approved' => $row['approved'],
                ],
            );
            $appointment->services()->sync([$services[0]->id]);
        }

        Review::query()->updateOrCreate(
            ['user_id' => $users[0]->id, 'car_wash_id' => $washes[0]->id],
            ['stars' => 5, 'text' => 'სწრაფი და სუფთა რეცხვა.', 'status' => true],
        );
        Review::query()->updateOrCreate(
            ['user_id' => $users[1]->id, 'car_wash_id' => $washes[1]->id],
            ['stars' => 4, 'text' => 'კარგი სერვისია, რიგი ცოტა იყო.', 'status' => false],
        );

        $food = VoucherCategory::query()->updateOrCreate(['name' => 'კვება'], []);
        $fuel = VoucherCategory::query()->updateOrCreate(['name' => 'საწვავი'], []);

        $voucher = Voucher::query()->updateOrCreate(
            ['name' => 'კაფე 20%'],
            [
                'description' => '20% ფასდაკლება სასმელზე',
                'photo' => '/images/shop/voucher_1.png',
                'category_id' => $food->id,
                'price' => 150,
                'percent' => 20,
                'count' => 50,
                'conditions' => 'ერთი ვაუჩერი ერთ შეკვეთაზე',
                'footer_text' => 'მოქმედებს თბილისში',
                'footer_geo' => '41.715,44.792',
                'deleted' => false,
            ],
        );
        Voucher::query()->updateOrCreate(
            ['name' => 'საწვავი 10 ლარი'],
            [
                'description' => '10 ლარის ვაუჩერი საწვავზე',
                'photo' => '/images/shop/voucher_2.png',
                'category_id' => $fuel->id,
                'price' => 300,
                'percent' => 0,
                'count' => 20,
                'conditions' => 'მინიმალური ჩასხმა 20 ლარი',
                'deleted' => false,
            ],
        );

        UserVoucher::query()->updateOrCreate(
            ['code' => 'GE22779A'],
            ['user_id' => $users[0]->id, 'voucher_id' => $voucher->id],
        );

        $ticket = Ticket::query()->updateOrCreate(
            ['name' => 'iPhone გათამაშება'],
            [
                'description' => 'ყოველთვიური გათამაშება',
                'photo' => '/images/shop/ticket_1.png',
                'price' => 50,
                'date_to' => now()->addMonth()->toDateString(),
                'count' => 100,
                'footer_text' => 'გამარჯვებული ცხადდება თვის ბოლოს',
            ],
        );
        UserTicket::query()->firstOrCreate([
            'user_id' => $users[1]->id,
            'ticket_id' => $ticket->id,
        ]);

        Partner::query()->updateOrCreate(
            ['login' => 'partner'],
            [
                'name' => 'კაფე ვაკე',
                'description' => 'პარტნიორი კაფე',
                'password' => Hash::make('partner123'),
            ],
        );

        Promo::query()->updateOrCreate(
            ['title' => 'პირველი რეცხვა'],
            [
                'description' => 'ახალ მომხმარებელს პირველი რეცხვა 30%-ით',
                'url' => 'https://gewash.ge',
            ],
        );

        Faq::query()->updateOrCreate(
            ['question' => 'როგორ ვიყიდო პაკეტი?'],
            ['answer' => 'აირჩიე მანქანის ტიპი და თვეების რაოდენობა აპში.'],
        );
        Contact::query()->updateOrCreate(
            ['email' => 'info@gewash.ge'],
            [
                'address' => 'თბილისი, ვაკე',
                'phone' => '032 2 00 00 00',
                'website' => 'https://gewash.ge',
                'working_hours' => '09:00 - 21:00',
            ],
        );

        unset($admin);
    }
}
