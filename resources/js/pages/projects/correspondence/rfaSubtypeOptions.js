export function buildRfaSubtypeOptions(subtypes = [], currentValue = '') {
    const options = subtypes.map((subtype) => ({
        id: subtype.id,
        code: subtype.code,
        name: subtype.name,
        legacy: false,
    }));
    const current = String(currentValue || '').trim();

    if (current && !options.some((option) => option.code === current)) {
        options.push({ id: null, code: current, name: 'Existing value', legacy: true });
    }

    return options;
}
