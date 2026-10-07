<?php

declare(strict_types=1);

use App\Actions\CreateMember;
use App\Mail\MagicLinkMail;
use App\Mail\WelcomeMail;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

function newMemberData(array $overrides = []): array
{
    return array_merge(['name' => 'Maria Nova', 'email' => 'Maria@Example.com', 'phone' => '600123123'], $overrides);
}

test('the membership role creates a member with name, email and phone', function () {
    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), newMemberData())
        ->assertRedirect(route('members.index'));

    $user = User::where('email', 'maria@example.com')->sole();

    expect($user->name)->toBe('Maria Nova')
        ->and($user->password)->toBeNull()
        ->and($user->member->phone)->toBe('600123123')
        ->and($user->member->is_active)->toBeTrue()
        ->and($user->member->household_id)->toBeNull()
        ->and($user->member->commissions)->toBeEmpty();
});

test('the owner and administrators create members', function (User $creator) {
    $this->actingAs($creator)
        ->post(route('members.store'), newMemberData())
        ->assertRedirect(route('members.index'));

    expect(User::where('email', 'maria@example.com')->first()?->member)->not->toBeNull();
})->with([
    'owner' => fn () => User::factory()->owner()->create(),
    'administrator' => fn () => User::factory()->administrator()->create(),
]);

test('a member without the membership role cannot create members', function () {
    $user = User::factory()->member(Household::factory()->create())->create();

    $this->actingAs($user)->post(route('members.store'), newMemberData())->assertForbidden();

    expect(User::where('email', 'maria@example.com')->exists())->toBeFalse();
});

test('a signed out visitor cannot create members', function () {
    $this->post(route('members.store'), newMemberData())->assertRedirect(route('login'));
});

test('the household and the commissions are optional and are set when given', function () {
    $household = Household::factory()->create();
    $commissions = Commission::factory()->count(2)->create();

    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), newMemberData([
            'household_id' => $household->id,
            'commission_ids' => $commissions->pluck('id')->all(),
        ]))
        ->assertRedirect(route('members.index'));

    $member = User::where('email', 'maria@example.com')->sole()->member;

    expect($member->household_id)->toBe($household->id)
        ->and($member->commissions->pluck('id')->sort()->values()->all())->toBe($commissions->pluck('id')->sort()->values()->all());
});

test('the household must still be active', function () {
    $household = Household::factory()->create(['is_active' => false]);

    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), newMemberData(['household_id' => $household->id]))
        ->assertSessionHasErrors('household_id');

    expect(User::where('email', 'maria@example.com')->exists())->toBeFalse();
});

test('a household or a commission that does not exist gives a validation error', function () {
    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), newMemberData([
            'household_id' => (string) Str::uuid7(),
            'commission_ids' => [(string) Str::uuid7()],
        ]))
        ->assertSessionHasErrors(['household_id', 'commission_ids']);
});

test('a household deactivated between validation and creation gives a validation error', function () {
    $household = Household::factory()->create();

    $creator = app(CreateMember::class);
    Household::whereKey($household->id)->update(['is_active' => false]);

    expect(fn () => $creator->handle('Maria', 'maria@example.com', null, $household->id, []))
        ->toThrow(ValidationException::class);
    expect(User::where('email', 'maria@example.com')->exists())->toBeFalse();
});

test('name and email are validated', function () {
    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), ['name' => '', 'email' => 'nope'])
        ->assertSessionHasErrors(['name', 'email']);
});

test('an email that already belongs to a member is rejected, ignoring case', function () {
    User::factory()->member(Household::factory()->create())->create(['email' => 'maria@example.com']);

    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), newMemberData(['email' => 'MARIA@example.com']))
        ->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
});

test('an email that belongs to an inactive member points to reactivating them', function () {
    $user = User::factory()->member(Household::factory()->create())->create(['email' => 'maria@example.com']);
    $user->member->update(['is_active' => false]);

    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), newMemberData())
        ->assertSessionHasErrors(['email' => __('This member is inactive: reactivate them instead of creating a new one.')]);
});

test('the owner\'s email is rejected: no profile is attached and no email is sent', function () {
    $owner = User::factory()->owner()->memberWithoutHousehold()->create(['email' => 'maria@example.com']);

    $this->actingAs(membershipRoleUser())
        ->post(route('members.store'), newMemberData(['email' => 'MARIA@Example.com']))
        ->assertSessionHasErrors(['email' => __('This email already belongs to a member.')]);

    expect(User::count())->toBe(2)->and($owner->fresh()->member()->count())->toBe(1);

    Mail::assertNothingQueued();
});

test('an email that belongs to a user without a member profile is rejected', function () {
    $admin = User::factory()->administrator()->create(['email' => 'maria@example.com']);

    $this->actingAs($admin)
        ->post(route('members.store'), newMemberData())
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(1)->and($admin->fresh()->member)->toBeNull();

    Mail::assertNothingQueued();
});

test('a double submit ends as a validation error on the email', function () {
    $creator = membershipRoleUser();

    $this->actingAs($creator)->post(route('members.store'), newMemberData())->assertRedirect(route('members.index'));
    $this->actingAs($creator)->post(route('members.store'), newMemberData())->assertSessionHasErrors('email');

    expect(User::where('email', 'maria@example.com')->count())->toBe(1);
    Mail::assertQueuedCount(1);
});

test('a race on the same email ends as a validation error on the email', function () {
    $creator = app(CreateMember::class);

    // The request check passed, then the other submit committed first.
    User::factory()->member(Household::factory()->create())->create(['email' => 'maria@example.com']);

    try {
        $creator->handle('Maria', 'maria@example.com', null, null, []);
        $this->fail('Expected a validation error');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('email');
    }
});

test('losing the unique email race inside the transaction ends as a validation error', function () {
    // The other submit commits its user between our lookup and our insert.
    User::creating(function (User $user) {
        DB::table('users')->insert(['id' => (string) Str::uuid7(), 'name' => 'Other', 'email' => $user->email, 'created_at' => now(), 'updated_at' => now()]);
    });

    try {
        app(CreateMember::class)->handle('Maria', 'maria@example.com', null, null, []);
        $this->fail('Expected a validation error');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('email');
    }

    expect(Member::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('a commission deleted between validation and creation gives a validation error', function () {
    $commission = Commission::factory()->create();
    $creator = app(CreateMember::class);
    $commission->delete();

    try {
        $creator->handle('Maria', 'maria@example.com', null, null, [$commission->id]);
        $this->fail('Expected a validation error');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('commission_ids');
    }

    expect(User::where('email', 'maria@example.com')->exists())->toBeFalse();
});

test('a phone of zero is kept', function () {
    $this->actingAs(membershipRoleUser())->post(route('members.store'), newMemberData(['phone' => '0']));

    expect(User::where('email', 'maria@example.com')->sole()->member->phone)->toBe('0');
});

test('a new member gets a welcome email with the sign-in link and no token', function () {
    $this->actingAs(membershipRoleUser())->post(route('members.store'), newMemberData());

    Mail::assertQueued(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('maria@example.com')
        && $mail->url === route('login')
        && $mail->name === 'Maria Nova');
    Mail::assertNotQueued(MagicLinkMail::class);

    $mail = new WelcomeMail(route('login'), 'Maria Nova');
    $mail->assertSeeInHtml(route('login'), false);
    expect($mail->envelope()->subject)->toBe(__('Welcome to :app', ['app' => config('app.name')]));
});

test('the welcome email is queued after the transaction commits', function () {
    expect(new WelcomeMail('x', 'y'))->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

test('the members screen offers the active households and the commissions', function () {
    Household::factory()->create(['is_active' => false]);
    Household::factory()->create();
    Commission::factory()->create();

    $this->actingAs(membershipRoleUser())
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page->has('commissions', 2)->has('households', 2));
});

test('a new member must set a password on first access, then waits for a household', function () {
    $this->actingAs(membershipRoleUser())->post(route('members.store'), newMemberData());
    $user = User::where('email', 'maria@example.com')->sole();

    auth()->forgetGuards();
    $this->flushSession();
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('password.setup'));
    $this->get(route('waiting'))->assertRedirect(route('password.setup'));

    $this->post(route('password.setup.store'), ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
        ->assertRedirect(route('home'));

    $this->get(route('dashboard'))->assertRedirect(route('waiting'));
    $this->get(route('waiting'))->assertOk();
});
