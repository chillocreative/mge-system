export const activeCategories = (materials) => [...new Set(materials.filter((material) => material.is_active).map((material) => material.category))].sort();

export const descriptionsForCategory = (materials, category) => materials
    .filter((material) => material.is_active && material.category === category)
    .map((material) => material.description).sort();
