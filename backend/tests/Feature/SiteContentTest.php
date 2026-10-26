<?php

use App\Models\CarWash;
use App\Models\SiteLead;
use App\Models\User;
use App\Support\SitePages;

it('serves the landing text in three languages with the defaults', function () {
    $res = $this->getJson('/api/site/home')->assertOk()->assertJsonPath('success', true);

    expect($res->json('text.ka')['hero.h1'])->toBe('რეცხვა გამოწერით')
        ->and($res->json('text.en')['hero.h1'])->toBe('Car wash, monthly')
        ->and($res->json('text.ru.plan.l.0'))->toContain('{n}')
        ->and($res->json('text.ka.faq.0.1'))->toBe('რამდენ ხანს მოქმედებს პაკეტი?')
        ->and($res->json('config.prices'))->toBe(['4' => 60, '8' => 110, '12' => 150])
        ->and(collect($res->json('sections'))->pluck('id')->all())->toBe(['hero', 'how', 'price', 'biz', 'feat', 'branches', 'app', 'faq'])
        ->and($res->json('branches'))->toBeNull();

    $this->getJson('/api/site/business')->assertOk()->assertJsonPath('text.en.doc', 'Autopass for business');
    $this->getJson('/api/site/settings')->assertNotFound();
});

it('lets the admin edit text, lists and sections, and the API shows it', function () {
    $faq = SitePages::lists('home')['faq']['items'];
    $faq[0]['en']['q'] = 'Edited question?';
    $faq[1]['delete'] = '1';
    $faq[] = ['ka' => ['q' => 'ახალი?', 'a' => 'დიახ'], 'en' => ['q' => 'New?', 'a' => 'Yes'], 'ru' => ['q' => '', 'a' => '']];
    $faq[] = ['ka' => ['q' => '', 'a' => ''], 'en' => ['q' => '', 'a' => ''], 'ru' => ['q' => '', 'a' => '']];

    $this->actingAs(cwAdmin())->post('/dashboard/site/home', [
        'texts' => ['hero.h1' => ['ka' => 'ახალი სათაური', 'en' => 'Car wash, monthly', 'ru' => 'Мойка по подписке']],
        'lists' => ['faq' => $faq],
        'sections' => ['feat' => ['visible' => '0', 'order' => '1'], 'hero' => ['visible' => '1', 'order' => '2']],
    ])->assertRedirect(route('admin.site.page', 'home'))->assertSessionHasNoErrors();

    expect(SitePages::stored('home')['texts'])->toBe(['hero.h1' => ['ka' => 'ახალი სათაური']]);

    $res = $this->getJson('/api/site/home')->assertOk();
    $count = count(SitePages::defaults('home')['lists']['faq']['items']);
    expect($res->json('text.ka')['hero.h1'])->toBe('ახალი სათაური')
        ->and($res->json('text.en.faq.0.1'))->toBe('Edited question?')
        ->and($res->json('text.en.faq'))->toHaveCount($count)
        ->and($res->json('text.en.faq.'.($count - 1).'.1'))->toBe('New?')
        ->and($res->json('text.ka.plan.l'))->toHaveCount(4)
        ->and($res->json('sections.0'))->toBe(['id' => 'feat', 'visible' => false])
        ->and($res->json('sections.1'))->toBe(['id' => 'hero', 'visible' => true]);

    $this->actingAs(cwAdmin())->post('/dashboard/site/home/reset')->assertRedirect();
    expect($this->getJson('/api/site/home')->json('text.ka')['hero.h1'])->toBe('რეცხვა გამოწერით');
});

it('takes prices, contacts and branches from the settings', function () {
    CarWash::create([
        'name' => 'ვაკე', 'address' => 'ჭავჭავაძე 1', 'work_time_start' => '09:00:00', 'work_time_end' => '21:00:00',
        'location' => '41.7,44.7', 'manager_id' => makeUser('555 70 00 01', User::ROLE_MANAGER)->id,
    ]);
    $this->getJson('/api/site/home'); // cached before the change

    $this->actingAs(cwAdmin())->post('/dashboard/site/settings', [
        'price_4' => '55', 'price_8' => '100', 'price_12' => '140', 'hours' => '08:00–22:00',
        'phone' => '+995 555 00 00 00', 'email' => 'info@example.ge', 'ios_url' => '', 'privacy_url' => 'https://example.ge/privacy', 'terms_url' => '',
        'branches_from_db' => '1',
    ])->assertSessionHasNoErrors();

    $res = $this->getJson('/api/site/home')->assertOk();
    expect($res->json('config.prices'))->toBe(['4' => 55, '8' => 100, '12' => 140])
        ->and($res->json('config.email'))->toBe('info@example.ge')
        ->and($res->json('config.privacy_url'))->toBe('https://example.ge/privacy')
        ->and($res->json('branches'))->toBe([['ვაკე', 'ჭავჭავაძე 1', '09:00–21:00']]);

    $this->actingAs(cwAdmin())->post('/dashboard/site/settings', ['price_4' => '-1', 'price_8' => '1', 'price_12' => '1', 'email' => 'nope'])
        ->assertSessionHasErrors(['price_4', 'email']);
});

it('stores a business request and shows it to the admin', function () {
    $this->postJson('/api/site/lead', ['company' => 'შპს ტესტი', 'phone' => '+995 555 12 34 56', 'lang' => 'ka'])
        ->assertOk()->assertJsonPath('success', true);
    $this->postJson('/api/site/lead', ['company' => '', 'phone' => 'abc'])->assertStatus(422)->assertJsonPath('success', false);

    $lead = SiteLead::query()->sole();
    $this->actingAs(cwAdmin())->get('/dashboard/site/leads')->assertOk()->assertSee('შპს ტესტი');
    $this->actingAs(cwAdmin())->post("/dashboard/site/leads/{$lead->id}/handle")->assertRedirect();
    expect($lead->fresh()->handled_at)->not->toBeNull();
    $this->actingAs(cwAdmin())->delete("/dashboard/site/leads/{$lead->id}")->assertRedirect();
    expect(SiteLead::query()->count())->toBe(0);
});

it('renders the editor pages and keeps them from other users', function () {
    $this->actingAs(cwAdmin())->get('/dashboard/site')->assertOk()->assertSee('hero.h1')->assertSee('რეცხვა გამოწერით');
    $this->actingAs(cwAdmin())->get('/dashboard/site/business')->assertOk()->assertSee('calc.h');
    $this->actingAs(cwAdmin())->get('/dashboard/site/settings')->assertOk();

    $this->actingAs(makeUser('555 11 22 33'))->post('/dashboard/site/home', ['texts' => []])->assertForbidden();
    expect(SitePages::stored('home'))->toBe([]);
});
