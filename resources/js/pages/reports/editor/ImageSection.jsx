import { useState } from 'react';
import { Link } from 'react-router-dom';

const input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500';

function CaptionGrid({ images, canEdit, onChange }) {
    if (!images.length) {
        return <p className="text-sm text-gray-500">No images selected for this section.</p>;
    }
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {images.map((img, idx) => (
                <div key={img.id ?? idx} className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="aspect-video w-full overflow-hidden bg-gray-50">
                        <img src={img.url} alt={img.caption || ''} className="h-full w-full object-cover" />
                    </div>
                    <div className="p-3">
                        <input
                            value={img.caption || ''}
                            onChange={(e) => onChange(idx, e.target.value)}
                            placeholder="Caption"
                            disabled={!canEdit}
                            className={`${input} disabled:bg-gray-100`}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

function PairsGrid({ pairs }) {
    if (!pairs.length) {
        return <p className="text-sm text-gray-500">No image pairs configured.</p>;
    }
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {pairs.map((p, idx) => (
                <div key={idx} className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <p className="border-b border-gray-100 px-3 py-2 text-sm font-medium text-gray-700">{p.label}</p>
                    <div className="grid grid-cols-2 gap-px bg-gray-100">
                        {['previous', 'current'].map((slot) => (
                            <div key={slot} className="aspect-video overflow-hidden bg-gray-50">
                                {p[slot]?.url ? (
                                    <img src={p[slot].url} alt={`${p.label} ${slot}`} className="h-full w-full object-cover" />
                                ) : (
                                    <div className="flex h-full items-center justify-center text-xs text-gray-400">No {slot} image</div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

// Thumbnails + editable captions for image sections (1.3 site pictures,
// 5.0 key plan / site access / progress-photo pairs). Images themselves are
// managed on the project's Report Data tab; this editor only edits captions.
export default function ImageSection({ config, projectId, merged, canEdit, onOverridesChange }) {
    const single = !!config.collection;
    const collections = config.collections || (single ? [config.collection] : []);

    const [state, setState] = useState(() => {
        const initial = {};
        collections.forEach((c) => {
            initial[c] = merged?.[c] || [];
        });
        return initial;
    });

    const updateCaption = (collection, idx, caption) => {
        const nextList = state[collection].map((img, i) => (i === idx ? { ...img, caption } : img));
        const next = { ...state, [collection]: nextList };
        setState(next);
        // Only send editable (caption-bearing) collections in overrides —
        // `pairs` (5.0) is display-only and not part of the payload.
        const payload = {};
        collections.forEach((c) => {
            if (c !== 'pairs') payload[c] = next[c];
        });
        onOverridesChange(payload);
    };

    return (
        <div className="space-y-6">
            {collections.map((c) => (
                <div key={c}>
                    {collections.length > 1 && (
                        <h3 className="mb-2 text-sm font-semibold capitalize text-gray-900">{c.replace('_', ' ')}</h3>
                    )}
                    {c === 'pairs' ? (
                        <PairsGrid pairs={state[c] || []} />
                    ) : (
                        <CaptionGrid images={state[c] || []} canEdit={canEdit} onChange={(idx, caption) => updateCaption(c, idx, caption)} />
                    )}
                </div>
            ))}
            {projectId && (
                <Link to={`/projects/${projectId}`} className="inline-block text-sm font-medium text-primary-700 hover:underline">
                    Manage images in Report Data
                </Link>
            )}
        </div>
    );
}
