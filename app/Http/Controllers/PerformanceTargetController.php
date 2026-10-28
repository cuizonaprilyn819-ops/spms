<?php

namespace App\Http\Controllers;

use App\Models\Dpcr;
use App\Models\Ipcr;
use App\Models\OfficeTarget;
use App\Models\Opcr;
use App\Models\PerformanceTarget;
use App\Models\Upcr;
use App\Services\PlanningOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerformanceTargetController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $officeTargets = OfficeTarget::with('createdBy');
        if ($user->hasAnyRole(['Supervisor', 'HR Head'])) {
            $officeTargets->whereHas('offices', fn ($q) => $q->whereKey($user->office_id ?? 0));
        } elseif ($user->hasRole('Campus Director')) {
            $officeTargets->where('created_by', $user->id);
        } elseif (! $user->hasRole('Administrator')) {
            $officeTargets->whereHas('assignedEmployees', fn ($q) => $q->whereKey($user->id));
        }
        $targets = PerformanceTarget::with(['targetable', 'createdBy'])
            ->currentPlanningRevision()
            ->where(fn ($q) => $q->whereNull('commitment_plan_id')->orWhereIn('commitment_plan_id', app(PlanningOrganization::class)->visiblePlanIds($user)))
            ->latest()
            ->paginate(10);

        return view('performance-targets.index', [
            'targets' => $targets,
            'officeTargets' => $officeTargets->latest()->paginate(10, ['*'], 'office_page')->withQueryString(),
            'typeLabels' => $this->typeLabels(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('office-targets.create');
    }

    /**
     * @return array<class-string<Model>, string>
     */
    private function typeLabels(): array
    {
        return [
            Opcr::class => 'OPCR',
            Dpcr::class => 'DPCR',
            Upcr::class => 'UPCR',
            Ipcr::class => 'IPCR',
        ];
    }
}
