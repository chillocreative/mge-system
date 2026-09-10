import { useState, useRef, useEffect } from 'react';

/**
 * A text search field that behaves like a select: type to filter options, pick
 * one from the suggestion list. Replaces native dropdowns where typing to find
 * an option is faster than scrolling. Keyboard: ↑/↓ to move, Enter to pick,
 * Esc to close. Clearing the field resets to no selection.
 *
 * Themed via `dark` for the dark glass dashboard vs. the default light pages.
 */
export default function SearchableSelect({
    value,
    onChange,
    options,
    placeholder = 'Search…',
    getLabel = (o) => o.label,
    getValue = (o) => o.value,
    dark = false,
    allowClear = true,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const boxRef = useRef(null);

    const selected = options.find((o) => String(getValue(o)) === String(value));
    const q = query.trim().toLowerCase();
    const filtered = q ? options.filter((o) => getLabel(o).toLowerCase().includes(q)) : options;

    useEffect(() => {
        const onDoc = (e) => { if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, []);

    useEffect(() => { setActive(0); }, [query, open]);

    const choose = (o) => { onChange(getValue(o)); setQuery(''); setOpen(false); };
    const clear = () => { onChange(''); setQuery(''); setOpen(false); };

    const onKeyDown = (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setOpen(true); setActive((a) => Math.min(a + 1, filtered.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)); }
        else if (e.key === 'Enter') { e.preventDefault(); if (open && filtered[active]) choose(filtered[active]); }
        else if (e.key === 'Escape') { setOpen(false); }
    };

    const t = dark
        ? {
            input: 'border-white/15 bg-white/10 text-emerald-50 placeholder:text-emerald-200/40 backdrop-blur focus:border-lime-400/60',
            icon: 'text-lime-300/70',
            menu: 'border-white/15 bg-[#0c3c20] shadow-black/40',
            item: 'text-emerald-50',
            itemActive: 'bg-white/10',
            itemSub: 'text-emerald-200/40',
            empty: 'text-emerald-200/40',
            clear: 'text-emerald-200/50 hover:text-emerald-50',
        }
        : {
            input: 'border-gray-300 bg-white text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500',
            icon: 'text-gray-400',
            menu: 'border-gray-200 bg-white shadow-lg',
            item: 'text-gray-800',
            itemActive: 'bg-primary-50',
            itemSub: 'text-gray-400',
            empty: 'text-gray-400',
            clear: 'text-gray-400 hover:text-gray-600',
        };

    return (
        <div ref={boxRef} className={`relative ${className}`}>
            <svg className={`pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 ${t.icon}`} fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" /></svg>
            <input
                type="text"
                value={open ? query : (selected ? getLabel(selected) : '')}
                placeholder={placeholder}
                onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
                onFocus={() => { setOpen(true); setQuery(''); }}
                onKeyDown={onKeyDown}
                className={`w-full rounded-lg border py-2 pl-9 pr-8 text-sm outline-none transition-colors ${t.input}`}
            />
            {allowClear && (value || (open && query)) && (
                <button type="button" onMouseDown={(e) => { e.preventDefault(); clear(); }} className={`absolute right-2.5 top-1/2 -translate-y-1/2 rounded p-0.5 ${t.clear}`} aria-label="Clear">
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            )}

            {open && (
                <ul className={`absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border py-1 ${t.menu}`}>
                    {filtered.length === 0 ? (
                        <li className={`px-3 py-2 text-sm ${t.empty}`}>No matches</li>
                    ) : filtered.map((o, i) => (
                        <li key={getValue(o)}>
                            <button
                                type="button"
                                onMouseDown={(e) => { e.preventDefault(); choose(o); }}
                                onMouseEnter={() => setActive(i)}
                                className={`block w-full px-3 py-2 text-left text-sm ${t.item} ${i === active ? t.itemActive : ''}`}
                            >
                                {getLabel(o)}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
