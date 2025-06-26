<?php
// app/Http/Controllers/Web/SettingsController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\System\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_settings');
    }

    public function index(): View
    {
        $user = Auth::user();
        $settings = $this->settingsService->getAllSettings($user->cooperative_id);

        return view('settings.index', compact('settings'));
    }

    public function general(): View
    {
        $user = Auth::user();
        $settings = $this->settingsService->getSettingsByCategory($user->cooperative_id, 'general');

        return view('settings.general', compact('settings'));
    }

    public function financial(): View
    {
        $user = Auth::user();
        $settings = $this->settingsService->getSettingsByCategory($user->cooperative_id, 'financial');

        return view('settings.financial', compact('settings'));
    }

    public function notifications(): View
    {
        $user = Auth::user();
        $settings = $this->settingsService->getSettingsByCategory($user->cooperative_id, 'notifications');

        return view('settings.notifications', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $this->settingsService->updateSettings(
                $user->cooperative_id,
                $request->settings ?? [],
                $user->id
            );

            return back()->with('success', 'Settings updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update settings: ' . $e->getMessage()]);
        }
    }

    public function backup(): View
    {
        $user = Auth::user();
        $backups = $this->settingsService->getBackupHistory($user->cooperative_id);

        return view('settings.backup', compact('backups'));
    }

    public function createBackup(): RedirectResponse
    {
        try {
            $user = Auth::user();

            $backup = $this->settingsService->createBackup($user->cooperative_id, $user->id);

            return back()->with('success', 'Backup created successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to create backup: ' . $e->getMessage()]);
        }
    }
}
