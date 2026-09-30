export const CONTRACT_CATEGORIES = [
    { value: 'subcontractor', label: 'MGE dengan Sub Contractor' },
    { value: 'client', label: 'MGE dengan Client' },
    { value: 'vendor_third_party', label: 'MGE dengan Vendor / 3rd Party' },
];

export const contractCategoryLabel = (value) => (
    CONTRACT_CATEGORIES.find((category) => category.value === value)?.label || '-'
);
