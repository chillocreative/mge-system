import assert from 'node:assert/strict';
import test from 'node:test';
import { buildRfaSubtypeOptions } from '../../resources/js/pages/projects/correspondence/rfaSubtypeOptions.js';

test('maps API RFA subtypes into selectable values', () => {
    const options = buildRfaSubtypeOptions([
        { id: 1, code: 'MA', name: 'Material Approval' },
        { id: 2, code: 'MS', name: 'Method Statement' },
    ]);

    assert.deepEqual(options, [
        { id: 1, code: 'MA', name: 'Material Approval', legacy: false },
        { id: 2, code: 'MS', name: 'Method Statement', legacy: false },
    ]);
});

test('keeps an existing correspondence subtype selectable when it is absent from the API list', () => {
    const options = buildRfaSubtypeOptions(
        [{ id: 1, code: 'MA', name: 'Material Approval' }],
        'LEGACY',
    );

    assert.deepEqual(options.at(-1), {
        id: null,
        code: 'LEGACY',
        name: 'Existing value',
        legacy: true,
    });
});

test('does not duplicate a current value already returned by the API', () => {
    const options = buildRfaSubtypeOptions(
        [{ id: 1, code: 'MA', name: 'Material Approval' }],
        'MA',
    );

    assert.equal(options.length, 1);
});
