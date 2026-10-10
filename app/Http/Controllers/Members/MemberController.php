<?php

declare(strict_types=1);

namespace App\Http\Controllers\Members;

use App\Actions\CreateMember;
use App\Actions\DeactivateMember;
use App\Actions\ReactivateMember;
use App\Auth\EmailChangeLevels;
use App\Auth\MemberRoles;
use App\Auth\MembershipRoleProtection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Models\Commission;
use App\Models\Household;
use App\Models\Member;
use App\Support\CatalanCollation;
use App\Support\MembersListRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request, EmailChangeLevels $emailChangeLevels, MembershipRoleProtection $protection, MemberRoles $memberRoles): Response|RedirectResponse
    {
        $actor = $request->user() ?? abort(403);

        // Sorted in the query so the list can be paginated, with the same
        // collation the PHP sort uses; the id breaks ties so pages stay stable.
        $query = CatalanCollation::orderByColumn(
            Member::query()
                ->select('members.*')
                ->join('users', 'users.id', '=', 'members.user_id')
                ->with(['user.administrator', 'household']),
            'users.name',
        )->orderBy('members.id');

        // Active members without a household (none, or an inactive one): the ones the membership commission still has to place.
        $filter = MembersListRedirect::filter($request->query('filter'));

        if ($filter === MembersListRedirect::FILTER_NO_HOUSEHOLD) {
            $query->withoutActiveHousehold()->where('members.is_active', true);
        }

        $lastPage = max(1, (int) ceil((clone $query)->toBase()->getCountForPagination() / self::PER_PAGE));
        $requestedPage = MembersListRedirect::page($request->query('page')) ?? 1;

        // Bounded before paginating: a huge page would overflow the offset.
        if ($requestedPage > $lastPage) {
            return redirect()->route('members.index', array_filter(['filter' => $filter, 'page' => $lastPage]));
        }

        $page = $query->paginate(self::PER_PAGE, page: $requestedPage)->withQueryString();

        // One lookup for the whole page: the per-row checks below read the remembered roles.
        $memberRoles->preload($page->getCollection());

        $members = $page->getCollection()->map(fn (Member $member) => [
            'id' => $member->id,
            'name' => $member->user->name,
            'email' => $member->user->email,
            'phone' => $member->phone,
            'household_id' => $member->household_id,
            'household_is_active' => $member->household?->is_active,
            // Only an inactive household is named: the picker lists the active ones, so this is the only way to tell which one it is.
            'household_number' => $member->household?->is_active === false ? $member->household->number : null,
            'household_name' => $member->household?->is_active === false ? $member->household->name : null,
            'is_active' => $member->is_active,
            'can_change_email' => $emailChangeLevels->canChange($actor, $member),
            'can_deactivate' => $protection->mayDeactivateMember($actor, $member),
            'can_reactivate' => $protection->mayReactivateMember($actor, $member),
        ])->values();

        $households = Household::query()
            ->where('is_active', true)
            ->orderBy('number')
            ->get(['id', 'number', 'name']);

        return Inertia::render('members/Index', [
            'members' => $members,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'prev_page_url' => $page->previousPageUrl(),
                'next_page_url' => $page->nextPageUrl(),
            ],
            'filter' => $filter,
            'households' => $households,
            'commissions' => Commission::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreMemberRequest $request, CreateMember $createMember): RedirectResponse
    {
        $validated = $request->safe();

        $createMember->handle(
            $validated->string('name')->toString(),
            $validated->string('email')->toString(),
            $validated->filled('phone') ? $validated->string('phone')->toString() : null,
            $validated->string('household_id')->toString() ?: null,
            array_values(array_map(strval(...), $validated->array('commission_ids'))),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member created.')]);

        return MembersListRedirect::back($request);
    }

    /**
     * The email applies at once, with no confirmation: the membership role
     * fixes details for people who cannot do it themselves. Name and email
     * live on the user, so an administrator profile of the same user follows.
     */
    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($member, $validated) {
            $member->user->forceFill(['name' => $validated['name']]);

            if (isset($validated['email'])) {
                // A self-service change requested earlier must not overwrite this
                // email later through its still-valid link.
                $member->user->forceFill([
                    'email' => $validated['email'],
                    'pending_email' => null,
                    'pending_email_token_hash' => null,
                    'pending_email_expires_at' => null,
                ]);
            }

            $member->user->save();
            $member->update(['phone' => $validated['phone'] ?? null]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return MembersListRedirect::back($request);
    }

    public function deactivate(Request $request, Member $member, DeactivateMember $deactivateMember, MembershipRoleProtection $protection): RedirectResponse
    {
        $actor = $request->user() ?? abort(403);
        abort_unless($protection->mayDeactivateMember($actor, $member), 403);

        abort_unless($deactivateMember->handleAs($member, $actor), 403);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member deactivated.')]);

        return MembersListRedirect::back($request);
    }

    public function reactivate(Request $request, Member $member, ReactivateMember $reactivateMember, MembershipRoleProtection $protection): RedirectResponse
    {
        abort_unless($protection->mayReactivateMember($request->user() ?? abort(403), $member), 403);

        $reactivateMember->handle($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member reactivated.')]);

        return MembersListRedirect::back($request);
    }
}
