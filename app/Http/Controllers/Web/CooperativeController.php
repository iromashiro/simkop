<?php
// app/Http/Controllers/Web/CooperativeController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Cooperative\Services\CooperativeService;
use App\Domain\Cooperative\Models\Cooperative;
use App\Domain\Cooperative\DTOs\CreateCooperativeDTO;
use App\Domain\Cooperative\DTOs\UpdateCooperativeDTO;
use App\Http\Requests\Web\Cooperative\CreateCooperativeRequest;
use App\Http\Requests\Web\Cooperative\UpdateCooperativeRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CooperativeController extends Controller
{
    public function __construct(
        private readonly CooperativeService $cooperativeService
    ) {
        $this->middleware('auth');
        $this->middleware('permission:manage_cooperative')->except(['show']);
    }

    /**
     * Display cooperative list
     */
    public function index(): View
    {
        $cooperatives = Cooperative::with(['users', 'members'])
            ->orderBy('name')
            ->paginate(20);

        return view('cooperative.index', compact('cooperatives'));
    }

    /**
     * Show create cooperative form
     */
    public function create(): View
    {
        return view('cooperative.create');
    }

    /**
     * Store new cooperative
     */
    public function store(CreateCooperativeRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateCooperativeDTO(
                name: $request->name,
                registrationNumber: $request->registration_number,
                address: $request->address,
                phone: $request->phone,
                email: $request->email,
                establishedDate: \Carbon\Carbon::parse($request->established_date),
                legalStatus: $request->legal_status,
                businessType: $request->business_type,
                createdBy: $user->id
            );

            $cooperative = $this->cooperativeService->createCooperative($dto);

            return redirect()
                ->route('cooperative.show', $cooperative)
                ->with('success', 'Cooperative created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create cooperative: ' . $e->getMessage()]);
        }
    }

    /**
     * Display cooperative details
     */
    public function show(Cooperative $cooperative): View
    {
        $cooperative->load(['users', 'members', 'settings']);

        $statistics = $this->cooperativeService->getCooperativeStatistics($cooperative->id);

        return view('cooperative.show', compact('cooperative', 'statistics'));
    }

    /**
     * Show edit cooperative form
     */
    public function edit(Cooperative $cooperative): View
    {
        return view('cooperative.edit', compact('cooperative'));
    }

    /**
     * Update cooperative
     */
    public function update(UpdateCooperativeRequest $request, Cooperative $cooperative): RedirectResponse
    {
        try {
            $dto = new UpdateCooperativeDTO(
                name: $request->name,
                address: $request->address,
                phone: $request->phone,
                email: $request->email,
                legalStatus: $request->legal_status,
                businessType: $request->business_type,
                isActive: $request->boolean('is_active')
            );

            $this->cooperativeService->updateCooperative($cooperative->id, $dto);

            return redirect()
                ->route('cooperative.show', $cooperative)
                ->with('success', 'Cooperative updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update cooperative: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete cooperative
     */
    public function destroy(Cooperative $cooperative): RedirectResponse
    {
        try {
            $this->cooperativeService->deleteCooperative($cooperative->id);

            return redirect()
                ->route('cooperative.index')
                ->with('success', 'Cooperative deleted successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete cooperative: ' . $e->getMessage()]);
        }
    }

    /**
     * Display cooperative settings
     */
    public function settings(Cooperative $cooperative): View
    {
        $settings = $this->cooperativeService->getCooperativeSettings($cooperative->id);

        return view('cooperative.settings', compact('cooperative', 'settings'));
    }

    /**
     * Update cooperative settings
     */
    public function updateSettings(Request $request, Cooperative $cooperative): RedirectResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        try {
            $user = Auth::user();

            $this->cooperativeService->updateCooperativeSettings(
                $cooperative->id,
                $request->settings,
                $user->id
            );

            return back()->with('success', 'Settings updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update settings: ' . $e->getMessage()]);
        }
    }
}
