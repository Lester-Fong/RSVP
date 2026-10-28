<?php

namespace App\Http\Controllers;

use App\Http\Requests\ViewAttendanceRequest;
use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\InvitationEligibility;
use App\Services\WeddingAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class WeddingAttendanceController extends Controller
{
    public function index(ViewAttendanceRequest $request, Account $account, Wedding $wedding): RedirectResponse
    {
        $filters = $request->filters();
        $filters['view'] = $request->validated('view') ?? 'guests';
        $eligibility = app(InvitationEligibility::class);
        if (! $eligibility->enabled) {
            $filters['invitation_state'] = 'active';
        }

        return to_route('accounts.weddings.guests.index', [
            'account' => $account, 'wedding' => $wedding, ...$filters,
            'page' => $request->validated('page') ?? 1,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function group(ViewAttendanceRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, WeddingAttendance $attendance): JsonResponse
    {
        $guests = DB::transaction(fn () => $attendance->guests($wedding, $request->filters(), $invitationGroup));

        return response()->json($guests, 200, ['Cache-Control' => 'private, no-store']);
    }
}
