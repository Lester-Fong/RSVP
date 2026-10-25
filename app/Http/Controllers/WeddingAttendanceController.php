<?php

namespace App\Http\Controllers;

use App\Http\Requests\ViewAttendanceRequest;
use App\Models\Account;
use App\Models\InvitationGroup;
use App\Models\Wedding;
use App\Services\InvitationEligibility;
use App\Services\WeddingAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class WeddingAttendanceController extends Controller
{
    public function index(ViewAttendanceRequest $request, Account $account, Wedding $wedding, WeddingAttendance $attendance): Response
    {
        $filters = $request->filters();
        $data = DB::transaction(function () use ($wedding, $attendance, $filters): array {
            $guests = $filters['view'] === 'guests' ? $attendance->guests($wedding, $filters) : null;
            $groups = $filters['view'] === 'groups' ? $attendance->groups($wedding, $filters) : null;

            return ['summary' => $attendance->summary($wedding), 'guests' => $guests, 'groups' => $groups,
                'matchedGuests' => $guests?->total() ?? $attendance->matchingGuests($wedding, $filters)->count()];
        });

        $response = Inertia::render('weddings/Attendance', [
            'account' => ['id' => $account->id, 'name' => $account->name],
            'wedding' => ['id' => $wedding->id, 'partner_one_name' => $wedding->partner_one_name, 'partner_two_name' => $wedding->partner_two_name],
            ...$data, 'filters' => $filters, 'refreshedAt' => now()->toIso8601String(),
            'withdrawalEnabled' => app(InvitationEligibility::class)->enabled,
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function group(ViewAttendanceRequest $request, Account $account, Wedding $wedding, InvitationGroup $invitationGroup, WeddingAttendance $attendance): JsonResponse
    {
        $guests = DB::transaction(fn () => $attendance->guests($wedding, $request->filters(), $invitationGroup));

        return response()->json($guests, 200, ['Cache-Control' => 'private, no-store']);
    }
}
