import test from 'node:test';
import assert from 'node:assert/strict';
import { activeCategories, descriptionsForCategory } from '../../resources/js/utils/materialOptions.js';

test('active material options filter descriptions by category', () => {
    const materials = [
        { category: 'Materials', description: 'Sand', is_active: true },
        { category: 'Materials', description: 'Gravel', is_active: false },
        { category: 'Equipment', description: 'Crane', is_active: true },
    ];
    assert.deepEqual(activeCategories(materials), ['Equipment', 'Materials']);
    assert.deepEqual(descriptionsForCategory(materials, 'Materials'), ['Sand']);
    assert.deepEqual(descriptionsForCategory(materials, 'Equipment'), ['Crane']);
    assert.deepEqual(descriptionsForCategory(materials, ''), []);
});
