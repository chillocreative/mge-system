import { createContext, useCallback, useContext, useRef, useState } from 'react';
import { HiOutlineExclamation } from 'react-icons/hi';

const ConfirmContext = createContext(null);

/**
 * App-wide replacement for the browser's native `confirm()`. Mount once at
 * the app root (see resources/js/app.jsx, alongside <Toaster />) and call
 * `useConfirm()` anywhere a destructive action needs a confirmation dialog
 * that matches the app's own visual style instead of the native browser
 * popup.
 */
export function ConfirmProvider({ children }) {
    const [options, setOptions] = useState(null);
    const resolverRef = useRef(null);

    const confirm = useCallback((opts = {}) => {
        return new Promise((resolve) => {
            resolverRef.current = resolve;
            setOptions({
                title: opts.title ?? 'Are you sure?',
                message: opts.message ?? 'This action cannot be undone.',
                confirmText: opts.confirmText ?? 'Delete',
                cancelText: opts.cancelText ?? 'Cancel',
                danger: opts.danger ?? true,
            });
        });
    }, []);

    const settle = (result) => {
        resolverRef.current?.(result);
        resolverRef.current = null;
        setOptions(null);
    };

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            {options && (
                <div
                    className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
                    onClick={() => settle(false)}
                >
                    <div
                        className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="confirm-dialog-title"
                    >
                        <div className="flex items-start gap-3">
                            <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${options.danger ? 'bg-red-100' : 'bg-amber-100'}`}>
                                <HiOutlineExclamation className={`h-5 w-5 ${options.danger ? 'text-red-600' : 'text-amber-600'}`} />
                            </div>
                            <div className="min-w-0">
                                <h3 id="confirm-dialog-title" className="text-base font-semibold text-gray-900">
                                    {options.title}
                                </h3>
                                <p className="mt-1 text-sm text-gray-500">{options.message}</p>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => settle(false)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                {options.cancelText}
                            </button>
                            <button
                                type="button"
                                autoFocus
                                onClick={() => settle(true)}
                                className={`rounded-lg px-4 py-2 text-sm font-semibold text-white ${
                                    options.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-primary-600 hover:bg-primary-700'
                                }`}
                            >
                                {options.confirmText}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </ConfirmContext.Provider>
    );
}

/**
 * Returns an async `confirm(options) => Promise<boolean>` function.
 * Usage: `if (!(await confirm({ message: 'Delete this item?' }))) return;`
 * Options: title, message, confirmText, cancelText, danger (bool, default true).
 */
export function useConfirm() {
    const confirm = useContext(ConfirmContext);
    if (!confirm) {
        throw new Error('useConfirm must be used within a ConfirmProvider');
    }

    return confirm;
}
