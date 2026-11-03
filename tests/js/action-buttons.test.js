import { test } from 'node:test';
import assert from 'node:assert/strict';
import { actionForLabel, tooltipPosition } from '../../resources/js/action-buttons.js';

test('distinguishes review, return, deletion and evidence actions', () => {
    assert.equal(actionForLabel('Approve commitments'), 'check');
    assert.equal(actionForLabel('Confirm return'), 'return');
    assert.equal(actionForLabel('Delete employee'), 'delete');
    assert.equal(actionForLabel('Reject proposal'), 'reject');
    assert.equal(actionForLabel('Submit MOV'), 'upload');
    assert.equal(actionForLabel('Reset password'), 'key');
    assert.equal(actionForLabel('PDF'), 'document');
    assert.equal(actionForLabel('Excel'), 'spreadsheet');
    assert.equal(actionForLabel('Processing Unit'), null);
});

test('long labels stay inside both horizontal viewport edges', () => {
    for (const left of [0, 790]) {
        const point = tooltipPosition({ left, width: 44, top: 300, bottom: 344 }, 300, 40, { width: 820, height: 600 });
        assert.ok(point.left >= 12);
        assert.ok(point.left + 300 <= 808);
        assert.equal(point.placement, 'above');
    }
});

test('a top-edge action shows its label below without escaping the screen', () => {
    const point = tooltipPosition({ left: 20, width: 44, top: 3, bottom: 47 }, 180, 40, { width: 400, height: 500 });
    assert.equal(point.placement, 'below');
    assert.ok(point.top >= 47);
    assert.ok(point.top + 40 <= 488);
});
