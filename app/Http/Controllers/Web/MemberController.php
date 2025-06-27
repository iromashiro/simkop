<?php
// app/Http/Controllers/Web/MemberController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Member\Services\MemberService;
use App\Domain\Member\DTOs\CreateMemberDTO;
use App\Domain\Member\DTOs\UpdateMemberDTO;
use App\Http\Requests\Web\Member\CreateMemberRequest;
use App\Http\Requests\Web\Member\UpdateMemberRequest;
use App\Domain\Member\Models\Member;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_members');
    }

    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Member::where('cooperative_id', $user->cooperative_id)
            ->with(['user'])
            ->orderBy('member_number');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('member_number', 'ILIKE', "%{$search}%")
                    ->orWhere('full_name', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $members = $query->paginate(20)->withQueryString();
        $statistics = $this->memberService->getMemberStatistics($user->cooperative_id);

        return view('members.index', compact('members', 'statistics'));
    }

    public function create(): View
    {
        return view('members.create');
    }

    public function store(CreateMemberRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateMemberDTO(
                cooperativeId: $user->cooperative_id,
                fullName: $request->full_name,
                email: $request->email,
                phone: $request->phone,
                address: $request->address,
                idNumber: $request->id_number,
                birthDate: $request->birth_date ? \Carbon\Carbon::parse($request->birth_date) : null,
                joinDate: \Carbon\Carbon::parse($request->join_date),
                initialDeposit: $request->initial_deposit ?? 0,
                createdBy: $user->id
            );

            $member = $this->memberService->createMember($dto);

            return redirect()
                ->route('members.show', $member)
                ->with('success', 'Member created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create member: ' . $e->getMessage()]);
        }
    }

    public function show(Member $member): View
    {
        $user = Auth::user();

        if ($member->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        $member->load(['user', 'savingsAccounts', 'loanAccounts']);
        $statistics = $this->memberService->getMemberStatistics($user->cooperative_id, $member->id);

        return view('members.show', compact('member', 'statistics'));
    }

    public function edit(Member $member): View
    {
        $user = Auth::user();

        if ($member->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        return view('members.edit', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        try {
            $user = Auth::user();

            if ($member->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $dto = new UpdateMemberDTO(
                fullName: $request->full_name,
                email: $request->email,
                phone: $request->phone,
                address: $request->address,
                idNumber: $request->id_number,
                birthDate: $request->birth_date ? \Carbon\Carbon::parse($request->birth_date) : null,
                status: $request->status
            );

            $this->memberService->updateMember($member->id, $dto);

            return redirect()
                ->route('members.show', $member)
                ->with('success', 'Member updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update member: ' . $e->getMessage()]);
        }
    }

    public function destroy(Member $member): RedirectResponse
    {
        try {
            $user = Auth::user();

            if ($member->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->memberService->deleteMember($member->id);

            return redirect()
                ->route('members.index')
                ->with('success', 'Member deleted successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete member: ' . $e->getMessage()]);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        return $this->memberService->exportMembers($user->cooperative_id, $request->all());
    }
}
