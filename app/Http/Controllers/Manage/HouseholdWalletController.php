<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\CorrectionRequest;
use App\Http\Requests\Manage\TopUpRequest;
use App\Http\WalletHouseholdRow;
use App\Http\WalletStatementRow;
use Guixa\Membership\Application\GetHousehold;
use Guixa\Membership\Application\HouseholdDetail;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\GetWalletStatement;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdWalletController extends Controller
{
    public function show(string $household, Actor $actor, GetHousehold $getHousehold, GetWalletStatement $getWalletStatement): Response
    {
        $detail = $this->existing($household, $actor, $getHousehold);

        return Inertia::render('Wallet/Statement', [
            'wallet' => WalletStatementRow::from($getWalletStatement($actor, $household)),
            'household' => WalletHouseholdRow::from($detail),
            'canManageReceipts' => true,
            'householdReturnHref' => route('manage.households.show', ['household' => $household]),
            'receiptAction' => route('manage.households.wallet.top-ups.store', ['household' => $household]),
            'correctionAction' => route('manage.households.wallet.corrections.store', ['household' => $household]),
            'correctionId' => (string) Str::uuid7(),
            'status' => session('status'),
        ]);
    }

    public function topUp(string $household, TopUpRequest $request, Actor $actor, GetHousehold $getHousehold, RecordTopUp $recordTopUp): RedirectResponse
    {
        $this->existing($household, $actor, $getHousehold);

        $recordTopUp($actor, $household, $request->amountCents(), $request->input('note'));

        return back()->with('status', __('Receipt recorded.'));
    }

    public function correct(string $household, CorrectionRequest $request, Actor $actor, GetHousehold $getHousehold, RecordCorrection $recordCorrection): RedirectResponse
    {
        $this->existing($household, $actor, $getHousehold);

        $recordCorrection($actor, $household, $request->amountCents(), $request->string('note')->toString(), null, $request->string('source_movement_id')->toString(), $request->string('correction_id')->toString());

        return back()->with('status', __('Correction recorded.'));
    }

    /** The wallet table has a foreign key to the household; answer 404 instead of letting it fail. */
    private function existing(string $household, Actor $actor, GetHousehold $getHousehold): HouseholdDetail
    {
        try {
            return $getHousehold($actor, $household);
        } catch (HouseholdNotFound) {
            abort(404);
        }
    }
}
