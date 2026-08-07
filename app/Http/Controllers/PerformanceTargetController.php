<?php

namespace App\Http\Controllers;

use App\Models\Dpcr;
use App\Models\Ipcr;
use App\Models\Opcr;
use App\Models\PerformanceTarget;
use App\Models\Upcr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PerformanceTargetController extends Controller
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const TARGETABLE_TYPES = [
        'opcr' => Opcr::class,
        'dpcr' => Dpcr::class,
        'upcr' => Upcr::class,
        'ipcr' => Ipcr::class,
    ];

    public function index(): View
    {
        $targets = PerformanceTarget::with(['targetable', 'createdBy'])
            ->latest()
            ->paginate(10);

        return view('performance-targets.index', [
            'targets' => $targets,
            'typeLabels' => $this->typeLabels(),
        ]);
    }

    public function create(): View
    {
        return view('performance-targets.create', [
            'targetables' => $this->targetableOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'targetable_type' => ['required', 'string', Rule::in(array_keys(self::TARGETABLE_TYPES))],
            'targetable_id' => ['required', 'integer'],
            'target' => ['required', 'string', 'max:255'],
            'success_indicator' => ['required', 'string', 'max:5000'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'deadline' => ['required', 'date'],
        ]);

        $targetable = $this->resolveTargetable($data['targetable_type'], (int) $data['targetable_id']);

        PerformanceTarget::create([
            'targetable_type' => $targetable::class,
            'targetable_id' => $targetable->getKey(),
            'target' => $data['target'],
            'success_indicator' => $data['success_indicator'],
            'weight' => $data['weight'],
            'deadline' => $data['deadline'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('performance-targets.index')
            ->with('status', 'Performance target added successfully.');
    }

    /**
     * @return array<string, \Illuminate\Database\Eloquent\Collection<int, Model>>
     */
    private function targetableOptions(): array
    {
        return [
            'OPCR' => Opcr::with(['evaluationCycle', 'office'])->latest()->get(),
            'DPCR' => Dpcr::with(['opcr.office', 'employee'])->latest()->get(),
            'UPCR' => Upcr::with(['dpcr.opcr.office', 'employee'])->latest()->get(),
            'IPCR' => Ipcr::with(['upcr.dpcr.opcr.office', 'employee'])->latest()->get(),
        ];
    }

    private function resolveTargetable(string $type, int $id): Model
    {
        /** @var class-string<Model> $model */
        $model = self::TARGETABLE_TYPES[$type];

        return $model::query()->findOrFail($id);
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
