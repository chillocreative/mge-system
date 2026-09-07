import { useEffect, useMemo, useRef, useState } from 'react';
import { HiOutlineChevronDown, HiOutlineX } from 'react-icons/hi';

/**
 * Searchable employee picker.
 *
 * Replaces the plain <select> that four screens each had a copy of. Two reasons
 * it needed to change:
 *
 * 1. Names are not distinctive enough to pick from a list. MGE currently has
 *    four staff whose name begins "SITI" and three beginning "NURUL" — scanning
 *    a dropdown for the right one is genuinely error-prone, and picking the
 *    wrong person on a leave form is not a harmless mistake. Showing the
 *    employee number alongside the name removes the ambiguity.
 *
 * 2. A plain dropdown is workable at fourteen staff and unusable at a hundred
 *    and forty. Typing to filter costs nothing today and keeps working later.
 *
 * Filtering matches the employee number as well as the name, so "PTG01" and
 * "nasuha" both find the same person.
 */
export default function EmployeeSelect({
    employees = [],
    value = '',
    onChange,
    placeholder = 'Search name or employee no…',
    disabled = false,
    required = false,
    allowClear = true,
    className = '',
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlighted, setHighlighted] = useState(0);
    const containerRef = useRef(null);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    const selected = useMemo(
        () => employees.find((e) => String(e.id) === String(value)) || null,
        [employees, value],
    );

    const label = (emp) => {
        const name = emp.full_name || [emp.first_name, emp.last_name].filter(Boolean).join(' ');
        return emp.employee_no ? `${emp.employee_no} — ${name}` : name;
    };

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return employees;
        return employees.filter((e) => label(e).toLowerCase().includes(q));
    }, [employees, query]);

    // Close when clicking away, and discard whatever was half-typed.
    useEffect(() => {
        const onDocClick = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', onDocClick);
        return () => document.removeEventListener('mousedown', onDocClick);
    }, []);

    useEffect(() => {
        if (open) setHighlighted(0);
    }, [open, query]);

    // Keep the highlighted row in view when navigating by keyboard.
    useEffect(() => {
        if (!open || !listRef.current) return;
        const el = listRef.current.querySelector(`[data-index="${highlighted}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    }, [highlighted, open]);

    const choose = (emp) => {
        onChange?.(emp ? String(emp.id) : '');
        setOpen(false);
        setQuery('');
    };

    const handleKeyDown = (e) => {
        if (disabled) return;

        if (!open && ['ArrowDown', 'Enter'].includes(e.key)) {
            e.preventDefault();
            setOpen(true);
            return;
        }
        if (!open) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlighted((i) => Math.min(i + 1, filtered.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlighted((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (filtered[highlighted]) choose(filtered[highlighted]);
        } else if (e.key === 'Escape') {
            setOpen(false);
            setQuery('');
        }
    };

    const base =
        'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-gray-50';

    return (
        <div ref={containerRef} className={`relative ${className}`}>
            <div className="relative">
                <input
                    ref={inputRef}
                    type="text"
                    role="combobox"
                    aria-expanded={open}
                    aria-controls="employee-select-list"
                    aria-autocomplete="list"
                    autoComplete="off"
                    disabled={disabled}
                    // Showing the selected label when closed keeps this readable
                    // at a glance; typing replaces it with the search term.
                    value={open ? query : selected ? label(selected) : ''}
                    placeholder={selected ? label(selected) : placeholder}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        if (!open) setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    onKeyDown={handleKeyDown}
                    className={`${base} pr-16`}
                />

                <div className="absolute inset-y-0 right-2 flex items-center gap-1">
                    {allowClear && selected && !disabled && (
                        <button
                            type="button"
                            aria-label="Clear selection"
                            onClick={() => choose(null)}
                            className="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
                            <HiOutlineX className="h-4 w-4" />
                        </button>
                    )}
                    <HiOutlineChevronDown
                        className={`h-4 w-4 text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`}
                    />
                </div>
            </div>

            {/* Mirrors the selection into the form so `required` still works —
                the visible input holds a search term, not the value. */}
            {required && (
                <input
                    type="text"
                    tabIndex={-1}
                    required
                    value={value || ''}
                    onChange={() => {}}
                    className="pointer-events-none absolute h-0 w-0 opacity-0"
                    aria-hidden="true"
                />
            )}

            {open && (
                <ul
                    id="employee-select-list"
                    ref={listRef}
                    role="listbox"
                    className="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                >
                    {filtered.length === 0 && (
                        <li className="px-3 py-2 text-sm text-gray-400">No employee matches “{query}”</li>
                    )}

                    {filtered.map((emp, index) => {
                        const isSelected = String(emp.id) === String(value);
                        return (
                            <li
                                key={emp.id}
                                data-index={index}
                                role="option"
                                aria-selected={isSelected}
                                onMouseEnter={() => setHighlighted(index)}
                                onClick={() => choose(emp)}
                                className={`cursor-pointer px-3 py-2 text-sm ${
                                    index === highlighted ? 'bg-primary-50 text-primary-900' : 'text-gray-700'
                                } ${isSelected ? 'font-medium' : ''}`}
                            >
                                {emp.employee_no && (
                                    <span className="mr-2 font-mono text-xs text-gray-400">{emp.employee_no}</span>
                                )}
                                {emp.full_name || [emp.first_name, emp.last_name].filter(Boolean).join(' ')}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
