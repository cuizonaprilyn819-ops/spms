<?php

namespace App\Http\Controllers;

use App\Models\TargetAssistance;
use App\Services\AiAssistant;
use App\Services\AssistanceForms;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class TargetAssistanceController extends Controller
{
    public function preview(Request $r, AssistanceForms $forms, AiAssistant $ai)
    {
        $d = $r->validate(['action' => 'required|string|max:1000', 'values' => 'required|array|max:100', 'field' => 'required|string|max:150']);
        [$subject, $fields] = $forms->resolve($d['action'], $d['values'], $r->user());
        abort_unless($forms->allowed($d['field'], $fields), 422, 'AI cannot edit this field.');
        $original = Arr::get($d['values'], $d['field']);
        abort_unless(is_string($original) && trim($original) !== '' && mb_strlen($original) <= 5000, 422, 'Enter text of up to 5,000 characters first.');
        $protected = $forms->protectedValues($d['values'], $subject);
        abort_if(strlen(json_encode($protected)) > 30000, 422, 'The supplied context is too large.');
        $record = TargetAssistance::create(['user_id' => $r->user()->id, 'subject_kind' => $forms->subjects->kind($subject), 'subject_id' => $subject->id, 'field' => $d['field'], 'fingerprint' => $forms->subjects->fingerprint($subject), 'input_hash' => hash('sha256', $original), 'protected_values' => $protected, 'original' => $original, 'status' => 'pending']);
        try {
            $suggestion = trim($ai->draft('rephrase', ['original' => $original, 'protected_values' => $protected]));
            preg_match_all('/\d+(?:[.,:\/-]\d+)*/u', $original, $before);
            preg_match_all('/\d+(?:[.,:\/-]\d+)*/u', $suggestion, $after);
            sort($before[0]);
            sort($after[0]);
            if ($before[0] !== $after[0] || mb_strlen($suggestion) > (in_array($d['field'], ['target', 'title', 'local_target'], true) ? 255 : 5000)) {
                throw new \RuntimeException('AI changed protected numbers or exceeded the field length. Keep the original or try again.');
            }
            $record->update(['suggestion' => $suggestion, 'status' => 'completed']);
        } catch (\RuntimeException $e) {
            $record->update(['status' => 'failed']);

            return response()->view('target-assistance.preview', ['assistance' => $record, 'failure' => $e->getMessage()], 503)->header('Cache-Control', 'no-store, private');
        }

        return response()->view('target-assistance.preview', ['assistance' => $record, 'failure' => null])->header('Cache-Control', 'no-store, private');
    }
}
