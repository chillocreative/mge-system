import assert from 'node:assert/strict';
import test from 'node:test';
import { compactPartyPayload, partyToForm } from '../../resources/js/pages/master-data/masterDataForm.js';

test('maps API contacts into the fixed main and additional PIC fields', () => {
    const form = partyToForm({
        name: 'Alpha',
        initial: 'ALP',
        categories: [{ id: 3 }],
        contacts: { main: { name: 'Main PIC' } },
    });

    assert.equal(form.contacts.main.name, 'Main PIC');
    assert.deepEqual(form.contacts.additional, { name: '', position: '', phone: '', email: '' });
    assert.deepEqual(form.category_ids, [3]);
});

test('normalizes initial and category ids for API submission', () => {
    const form = partyToForm({ name: 'Beta', initial: 'bet', categories: [{ id: '4' }] });
    const payload = compactPartyPayload(form);

    assert.equal(payload.initial, 'BET');
    assert.deepEqual(payload.category_ids, [4]);
});
