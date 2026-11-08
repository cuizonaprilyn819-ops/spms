<?php

namespace Tests\Unit;

use App\Models\EvidenceAnalysis;
use Tests\TestCase;

class MovProcessingFeedbackTest extends TestCase
{
    public function test_feedback_distinguishes_waiting_running_and_delayed_processing(): void
    {
        $analysis = new EvidenceAnalysis(['status' => 'queued', 'updated_at' => now(), 'findings' => ['processing' => ['state' => 'queued']]]);
        $this->assertSame('queued', $analysis->processingFeedback()['status']);
        $this->assertSame('', $analysis->processingFeedback()['warning']);
        $analysis->updated_at = now()->subMinutes(3);
        $this->assertStringContainsString('Still waiting for a worker', $analysis->processingFeedback()['warning']);
        // Preview generation starts before the analysis status changes.
        $analysis->findings = ['processing' => ['state' => 'running']];
        $analysis->updated_at = now();
        $this->assertSame('analyzing', $analysis->processingFeedback()['status']);
        $this->assertSame('Analyzing your MOV', $analysis->processingFeedback()['label']);
        $analysis->updated_at = now()->subMinutes(6);
        $this->assertStringContainsString('longer than expected', $analysis->processingFeedback()['warning']);
    }
}
