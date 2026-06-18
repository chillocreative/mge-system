export default function Logo({ size = 40, showText = false, variant = 'dark', className = '' }) {
    const isLight = variant === 'light';

    return (
        <div className={`flex items-center gap-2.5 ${className}`}>
            <span
                className="flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1 ring-1 ring-black/5 shadow-sm"
                style={{ height: size, width: size }}
            >
                <img
                    src="/logo.png"
                    alt="Multi Green Engineering"
                    className="h-full w-full object-contain"
                />
            </span>
            {showText && (
                <span
                    className={`text-lg font-bold tracking-tight ${
                        isLight ? 'text-white' : 'text-primary-700'
                    }`}
                >
                    MGE-PMS
                </span>
            )}
        </div>
    );
}
