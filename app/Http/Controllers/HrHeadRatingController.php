<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Ipcr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class HrHeadRatingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $role = $this->role($request);
        $personnel = $this->personnel($role);

        $evaluations = Evaluation::with(['employee.office', 'employee.roles', 'performanceTarget', 'ratedBy'])
            ->whereHas('employee', $personnel)
            ->latest()
            ->paginate(10, ['*'], 'evaluations_page')
            ->withQueryString();

        $ipcrRatings = Ipcr::with(['employee.office', 'employee.roles', 'approvedBy'])
            ->where('status', Ipcr::STATUS_APPROVED)
            ->whereHas('employee', $personnel)
            ->latest('approved_at')
            ->paginate(10, ['*'], 'ipcr_page')
            ->withQueryString();

        $ratedIpcrCount = Ipcr::where('status', Ipcr::STATUS_APPROVED)
            ->whereNotNull('average_rating')
            ->whereHas('employee', $personnel)
            ->count();

        $unratedIpcrCount = Ipcr::where('status', Ipcr::STATUS_APPROVED)
            ->whereNull('average_rating')
            ->whereHas('employee', $personnel)
            ->count();

        return view('ratings.hr-head', compact('evaluations', 'ipcrRatings', 'ratedIpcrCount', 'unratedIpcrCount', 'role'));
    }

    public function pdf(Request $request): Response
    {
        $role = $this->role($request);
        $personnel = $this->personnel($role);

        $evaluations = Evaluation::with(['employee.office', 'performanceTarget', 'ratedBy'])
            ->whereHas('employee', $personnel)->latest()->get();
        $ipcrRatings = Ipcr::with(['employee.office', 'approvedBy'])
            ->where('status', Ipcr::STATUS_APPROVED)
            ->whereNotNull('average_rating')
            ->whereHas('employee', $personnel)
            ->latest('approved_at')->get();

        return Pdf::loadView('ratings.pdf', compact('evaluations', 'ipcrRatings', 'role'))
            ->setPaper('a4', 'landscape')
            ->download(($role ? strtolower($role) : 'all').'-ratings-'.now()->format('Y-m-d').'.pdf');
    }

    private function role(Request $request): ?string
    {
        $role = $request->query('role');

        return in_array($role, ['Employee', 'Supervisor'], true) ? $role : null;
    }

    private function personnel(?string $role): \Closure
    {
        return static function ($query) use ($role): void {
            $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', $role ? [$role] : ['Employee', 'Supervisor']));
        };
    }
}
