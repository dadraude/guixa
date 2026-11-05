<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\CorrectionRequest;
use App\Http\Requests\Manage\TopUpRequest;
use App\Http\WalletHouseholdRow;
use App\Http\WalletStatementRow;
use Guixa\Membership\Application\GetWalletHousehold;
use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\GetWalletStatement;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class HouseholdWalletController extends Controller
{
    public function show(string $household, Actor $actor, GetWalletHousehold $getHousehold, GetWalletStatement $getStatement): Response
    {
        try {
            $name = $getHousehold($actor, $household);
        } catch (HouseholdNotFound) {
            abort(404);
        }

        return Inertia::render('Wallet/Statement', [
            'wallet' => WalletStatementRow::from($getStatement($actor, $household)),
            'household' => new WalletHouseholdRow($name->id, $name->name),
            'canManageReceipts' => true,
            ...(Gate::forUser($actor)->allows('manage') ? ['householdReturnHref' => route('manage.households.show', ['household' => $household])] : []),
            'receiptAction' => route('finance.households.wallet.top-ups.store', ['household' => $household]),
            'correctionAction' => route('finance.households.wallet.corrections.store', ['household' => $household]),
            'correctionId' => (string) Str::uuid7(),
            'status' => session('status'),
        ]);
    }

    public function topUp(string $household, TopUpRequest $request, Actor $actor, GetWalletHousehold $getHousehold, RecordTopUp $recordTopUp): RedirectResponse
    {
        $this->existing($household, $actor, $getHousehold);
        $recordTopUp($actor, $household, $request->amountCents(), $request->input('note'));

        return back()->with('status', __('Receipt recorded.'));
    }

    public function correct(string $household, CorrectionRequest $request, Actor $actor, GetWalletHousehold $getHousehold, RecordCorrection $recordCorrection): RedirectResponse
    {
        $this->existing($household, $actor, $getHousehold);
        $recordCorrection($actor, $household, $request->amountCents(), $request->string('note')->toString(), null, $request->string('source_movement_id')->toString(), $request->string('correction_id')->toString());

        return back()->with('status', __('Correction recorded.'));
    }

    private function existing(string $household, Actor $actor, GetWalletHousehold $getHousehold): void
    {
        try {
            $getHousehold($actor, $household);
        } catch (HouseholdNotFound) {
            abort(404);
        }
    }
}
