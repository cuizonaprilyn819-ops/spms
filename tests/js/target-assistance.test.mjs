import { test } from 'node:test';
import assert from 'node:assert/strict';
import { assistanceFields } from '../../resources/js/target-assistance.js';

test('all target form families have explicit wording fields', () => {
    assert.deepEqual(assistanceFields('/api/planning/targets/5/update'), ['target', 'success_indicator', 'local_target']);
    assert.deepEqual(assistanceFields('/planning/5/targets'), ['target', 'success_indicator', 'local_target']);
    assert.deepEqual(assistanceFields('/planning/proposals/5/resubmit'), ['target', 'success_indicator', 'local_target']);
    assert.deepEqual(assistanceFields('/office-targets/5'), ['title', 'success_indicator']);
    assert.deepEqual(assistanceFields('/target-drafts/5/assign'), ['title', 'success_indicator']);
    assert.deepEqual(assistanceFields('/report-workflow/ipcr/5'), ['title', 'strategic_program', 'program_outcome', 'success_indicators', 'targets']);
    assert.deepEqual(assistanceFields('/evidence/planning-output/5/statement'), ['statement']);
});
test('historical sources and unrelated forms cannot be rephrased', () => {
    assert.deepEqual(assistanceFields('/historical-pcrs/5'), ['snapshot.notes']);
    assert.deepEqual(assistanceFields('/historical-pcrs/5/corrections'), ['message']);
    assert.deepEqual(assistanceFields('/employees/5'), []);
    assert.deepEqual(assistanceFields('/planning/5/actions/approve'), []);
});
