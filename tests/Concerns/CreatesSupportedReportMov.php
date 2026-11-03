<?php

namespace Tests\Concerns;

use App\Models\{Mov, PerformanceTarget};
use Illuminate\Support\Facades\Storage;

trait CreatesSupportedReportMov
{
    private bool $supportedMovDiskReady = false;

    private function addSupportedReportMov($report): void
    {
        if (! $this->supportedMovDiskReady) {
            Storage::fake('public');
            Storage::fake('local');
            $this->supportedMovDiskReady = true;
        }
        if ($report instanceof \App\Models\Ipcr && $report->office_target_id) {
            $path = 'test-movs/ipcr-'.$report->id.'.pdf';
            Storage::disk('local')->put($path, '%PDF synthetic evidence fixture');
            $mov = \App\Models\TargetMov::create(['office_target_id' => $report->office_target_id, 'user_id' => $report->employee_id, 'office_id' => $report->employee->office_id, 'accomplishment' => 'Target completed.', 'path' => $path, 'original_name' => 'proof.pdf']);
            $mov->analysis()->create(['status' => 'completed', 'findings' => ['supported' => true, 'satisfies_target' => true]]);
            return;
        }
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $report]);
        $path = 'test-movs/'.$target->id.'.pdf';
        Storage::disk('public')->put($path, '%PDF synthetic evidence fixture');
        $mov = Mov::create(['performance_target_id' => $target->id, 'uploaded_by' => $report->employee_id ?? $report->prepared_by, 'title' => 'Verified MOV fixture', 'file_path' => $path, 'original_name' => 'proof.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'file_size' => 32]);
        $mov->analysis()->create(['status' => 'completed', 'summary' => 'Evidence satisfies the test target.', 'statement' => 'Target completed.', 'findings' => ['supported' => true, 'satisfies_target' => true, 'target_assessment' => 'Confirmed complete.', 'source_excerpts' => ['Page 1: completion documented.']]]);
    }
}
