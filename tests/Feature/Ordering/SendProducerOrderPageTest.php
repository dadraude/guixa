<?php

use App\Models\User;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Ordering\Infrastructure\Membership\ProducerNotifierMail;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** A cycle with one baker, a Loaf ordered by a household, and a cutoff that is still ahead. */
function sendWorld(): array
{
    $household = (string) Str::uuid7();
    DB::table('households')->insert(['id' => $household, 'name' => 'Private Household', 'created_at' => now(), 'updated_at' => now()]);
    $baker = (string) Str::uuid7();
    DB::table('producers')->insert(['id' => $baker, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);
    $loaf = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $loaf, 'producer_id' => $baker, 'name' => 'Loaf', 'unit' => 'unit', 'price_cents' => 300, 'variable' => false, 'active' => true]);
    $cycle = (string) Str::uuid7();
    DB::table('cycles')->insert(['id' => $cycle, 'pickup_date' => now()->addDays(30)->toDateString()]);
    DB::table('cycle_producers')->insert(['cycle_id' => $cycle, 'producer_id' => $baker, 'participating' => true, 'closes_at' => null]);
    app(SetOrderLine::class)(Actor::memberOf(User::factory()->create()->id, $household), $cycle, $loaf, '2');

    return [$cycle, $baker];
}

function closeProducer(string $cycle, string $producer): void
{
    DB::table('cycle_producers')->where('cycle_id', $cycle)->where('producer_id', $producer)
        ->update(['closes_at' => now()->subMinute()->toIso8601String()]);
}

function sentAt(string $cycle, string $producer): ?string
{
    return DB::table('cycle_producers')->where('cycle_id', $cycle)->where('producer_id', $producer)->value('sent_at');
}

it('keeps the send route to managers (R12a)', function () {
    [$cycle, $baker] = sendWorld();
    closeProducer($cycle, $baker);
    $uri = "/manage/cycles/{$cycle}/producers/{$baker}/send";

    $this->post($uri)->assertRedirect(route('login'));
    $this->actingAs(accountWith(Role::Member, Role::Producer))->post($uri)->assertForbidden();

    expect(sentAt($cycle, $baker))->toBeNull();
});

it('lets a manager send a closed producer order and tells the producer (S36, R12a)', function () {
    Mail::fake();
    [$cycle, $baker] = sendWorld();
    User::factory()->create(['producer_id' => $baker, 'email' => 'baker@example.test']);
    closeProducer($cycle, $baker);

    $this->actingAs(accountWith(Role::Manager))
        ->post("/manage/cycles/{$cycle}/producers/{$baker}/send")
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(sentAt($cycle, $baker))->not->toBeNull();
    Mail::assertSent(ProducerNotifierMail::class, fn (ProducerNotifierMail $mail) => $mail->hasTo('baker@example.test')
        && $mail->totals === [['name' => 'Loaf', 'unit' => 'unit', 'quantity' => '2.000']]);
});

it('refuses to send a producer that is still open (S36)', function () {
    Mail::fake();
    [$cycle, $baker] = sendWorld();
    User::factory()->create(['producer_id' => $baker]);

    $this->actingAs(accountWith(Role::Manager))->from('/manage/cycles')
        ->post("/manage/cycles/{$cycle}/producers/{$baker}/send")
        ->assertRedirect('/manage/cycles')
        ->assertSessionHasErrors(['domain' => __('errors.ProducerStillOpen')]);

    expect(sentAt($cycle, $baker))->toBeNull();
    Mail::assertNothingSent();
});

it('refuses to send an order twice (R12a)', function () {
    Mail::fake();
    [$cycle, $baker] = sendWorld();
    User::factory()->create(['producer_id' => $baker]);
    closeProducer($cycle, $baker);
    $manager = accountWith(Role::Manager);
    $this->actingAs($manager)->post("/manage/cycles/{$cycle}/producers/{$baker}/send");

    $this->actingAs($manager)->from('/manage/cycles')
        ->post("/manage/cycles/{$cycle}/producers/{$baker}/send")
        ->assertSessionHasErrors(['domain' => __('errors.ProducerOrderSent')]);

    Mail::assertSent(ProducerNotifierMail::class, 1);
});

it('refuses to send when the producer has no account to notify (R12a)', function () {
    Mail::fake();
    [$cycle, $baker] = sendWorld();
    closeProducer($cycle, $baker);

    $this->actingAs(accountWith(Role::Manager))->from('/manage/cycles')
        ->post("/manage/cycles/{$cycle}/producers/{$baker}/send")
        ->assertSessionHasErrors(['domain' => 'Aquest productor no té cap compte per rebre la notificació de la comanda.']);

    expect(sentAt($cycle, $baker))->toBeNull();
    Mail::assertNothingSent();
});

it('refuses to send in a cancelled cycle (R4b)', function () {
    Mail::fake();
    [$cycle, $baker] = sendWorld();
    User::factory()->create(['producer_id' => $baker]);
    closeProducer($cycle, $baker);
    DB::table('cycles')->where('id', $cycle)->update(['cancelled_at' => now()]);

    $this->actingAs(accountWith(Role::Manager))->from('/manage/cycles')
        ->post("/manage/cycles/{$cycle}/producers/{$baker}/send")
        ->assertSessionHasErrors('domain');

    expect(sentAt($cycle, $baker))->toBeNull();
    Mail::assertNothingSent();
});

it('leaves an order whose every line is marked not delivered to the scheduler (R3b, R12a, D10)', function () {
    Mail::fake();
    [$cycle, $baker] = sendWorld();
    User::factory()->create(['producer_id' => $baker]);
    closeProducer($cycle, $baker);
    DB::table('products')->where('producer_id', $baker)->update(['active' => false]);
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.participants.0.canSendManually', false));

    $this->actingAs($manager)->from('/manage/cycles')
        ->post("/manage/cycles/{$cycle}/producers/{$baker}/send")
        ->assertSessionHasErrors(['domain' => __('errors.ProducerOrderEmpty')]);

    expect(sentAt($cycle, $baker))->toBeNull();
    Mail::assertNothingSent();
});

it('flags only a closed, participating, unsent producer with lines to send (R12a)', function () {
    [$cycle, $baker] = sendWorld();
    User::factory()->create(['producer_id' => $baker]);
    $manager = accountWith(Role::Manager);
    $ready = fn (bool $expected) => $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.participants.0.canSendManually', $expected));

    $sent = fn (bool $expected) => $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.participants.0.sent', $expected));

    $ready(false);
    $sent(false);
    closeProducer($cycle, $baker);
    $ready(true);
    $this->actingAs($manager)->post("/manage/cycles/{$cycle}/producers/{$baker}/send");
    $ready(false);
    $sent(true);
});

it('leaves a closed producer with no line to send to the scheduler (R12a, D10)', function () {
    Mail::fake();
    [$cycle, $baker] = sendWorld();
    DB::table('order_lines')->delete();
    closeProducer($cycle, $baker);
    $manager = accountWith(Role::Manager);

    $this->actingAs($manager)->get('/manage/cycles')
        ->assertInertia(fn (Assert $page) => $page->where('cycles.0.participants.0.canSendManually', false));

    $this->actingAs($manager)->from('/manage/cycles')
        ->post("/manage/cycles/{$cycle}/producers/{$baker}/send")
        ->assertSessionHasErrors(['domain' => 'La comanda d’aquest productor és buida: s’envia automàticament quan es tanca.']);

    expect(sentAt($cycle, $baker))->toBeNull();
    Mail::assertNothingSent();
});
