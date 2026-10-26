<?php

namespace Tests\Integration\Ordering;

use App\Models\User;
use Guixa\Ordering\Application\SendEmptyProducerOrders;
use Guixa\Ordering\Application\SendProducerOrder;
use Guixa\Ordering\Application\SetOrderLine;
use Guixa\Ordering\Domain\Error\ProducerHasNoAccount;
use Guixa\Ordering\Infrastructure\Membership\ProducerNotifierMail;
use Guixa\Shared\Application\Actor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The real adapters: the producer's account email, the stored sent state and marks, and the scheduled command. */
final class SendProducerOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $producer;

    private string $household;

    private string $cycle;

    private string $anna;

    /** @var list<string> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = (string) Str::uuid7();
        DB::table('households')->insert(['id' => $this->household, 'name' => 'H', 'created_at' => now(), 'updated_at' => now()]);
        $this->anna = User::factory()->create()->id;
        $this->producer = (string) Str::uuid7();
        DB::table('producers')->insert(['id' => $this->producer, 'name' => 'Baker', 'active' => true, 'cutoff_days_before' => 3, 'cutoff_time' => '20:00']);

        foreach (['Loaf', 'Jam'] as $name) {
            $this->products[] = $id = (string) Str::uuid7();
            DB::table('products')->insert(['id' => $id, 'producer_id' => $this->producer, 'name' => $name, 'unit' => 'unit', 'price_cents' => 300, 'variable' => false, 'active' => true]);
        }

        $this->cycle = (string) Str::uuid7();
        DB::table('cycles')->insert(['id' => $this->cycle, 'pickup_date' => now()->addDays(30)->toDateString()]);
        DB::table('cycle_producers')->insert(['cycle_id' => $this->cycle, 'producer_id' => $this->producer, 'participating' => true, 'closes_at' => null]);
    }

    private function manager(): Actor
    {
        return Actor::manager((string) Str::uuid7());
    }

    private function orderAndClose(): void
    {
        $member = Actor::memberOf($this->anna, $this->household);
        app(SetOrderLine::class)($member, $this->cycle, $this->products[0], '2');
        app(SetOrderLine::class)($member, $this->cycle, $this->products[1], '1');
        DB::table('cycle_producers')->where('cycle_id', $this->cycle)->update(['closes_at' => now()->subMinute()->toIso8601String()]);
        DB::table('products')->where('id', $this->products[1])->update(['active' => false]);
    }

    private function sentAt(): ?string
    {
        return DB::table('cycle_producers')->where('cycle_id', $this->cycle)->value('sent_at');
    }

    #[Test]
    public function sending_emails_the_producer_account_the_totals_and_stores_the_marks(): void
    {
        Mail::fake();
        User::factory()->create(['producer_id' => $this->producer, 'email' => 'baker@example.test']);
        $this->orderAndClose();

        app(SendProducerOrder::class)($this->manager(), $this->cycle, $this->producer);

        Mail::assertSent(ProducerNotifierMail::class, fn (ProducerNotifierMail $mail) => $mail->hasTo('baker@example.test')
            && $mail->totals === [['name' => 'Loaf', 'unit' => 'unit', 'quantity' => '2.000']]);
        $this->assertNotNull($this->sentAt());
        $this->assertSame([$this->products[1]], DB::table('order_lines')->where('not_delivered', true)->pluck('product_id')->all());
    }

    #[Test]
    public function sending_a_producer_without_account_stores_nothing_and_sends_nothing(): void
    {
        Mail::fake();
        $this->orderAndClose();

        $this->assertThrows(fn () => app(SendProducerOrder::class)($this->manager(), $this->cycle, $this->producer), ProducerHasNoAccount::class);

        Mail::assertNothingSent();
        $this->assertNull($this->sentAt());
        $this->assertSame(0, DB::table('order_lines')->where('not_delivered', true)->count());
    }

    #[Test]
    public function the_scheduled_command_sends_a_closed_producer_with_no_line_to_send_without_email(): void
    {
        Mail::fake();
        DB::table('cycle_producers')->where('cycle_id', $this->cycle)->update(['closes_at' => now()->subMinute()->toIso8601String()]);

        app(SendEmptyProducerOrders::class)();

        $this->assertNotNull($this->sentAt());
        Mail::assertNothingSent();
    }

    #[Test]
    public function the_scheduler_runs_the_command_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'send-empty-producer-orders');

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }
}
