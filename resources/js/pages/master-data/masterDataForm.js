export const blankContact = () => ({ name: '', position: '', phone: '', email: '' });

export const emptyPartyForm = () => ({
    name: '', initial: '', category_ids: [], address: '', city: '', state: '',
    country: '', postcode: '', website: '', is_active: true,
    contacts: { main: blankContact(), additional: blankContact() },
});

export function partyToForm(party) {
    const base = emptyPartyForm();

    return {
        ...base,
        name: party?.name || '',
        initial: party?.initial || '',
        category_ids: (party?.categories || []).map((category) => category.id),
        address: party?.address || '',
        city: party?.city || '',
        state: party?.state || '',
        country: party?.country || '',
        postcode: party?.postcode || '',
        website: party?.website || '',
        is_active: party?.is_active ?? true,
        contacts: {
            main: { ...base.contacts.main, ...(party?.contacts?.main || {}) },
            additional: { ...base.contacts.additional, ...(party?.contacts?.additional || {}) },
        },
    };
}

export function compactPartyPayload(form) {
    return {
        ...form,
        name: form.name.trim(),
        initial: form.initial.trim().toUpperCase(),
        category_ids: form.category_ids.map(Number),
    };
}
