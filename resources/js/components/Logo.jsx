export default function Logo({ size = 40, showText = false, variant = 'dark', className = '' }) {
    const isLight = variant === 'light';

    return (
        <div className={`flex items-center gap-2.5 ${className}`}>
            <svg
                width={size}
                height={size}
                viewBox="0 0 40 40"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                {/* Background */}
                <rect
                    width="40"
                    height="40"
                    rx="8"
                    fill={isLight ? 'rgba(255,255,255,0.1)' : '#0f172a'}
                />
                {/* Structural M — like steel beams */}
                <path
                    d="M10 30V12L20 22L30 12V30"
                    stroke="#f1f5f9"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                {/* Safety yellow base accent */}
                <line
                    x1="8"
                    y1="35"
                    x2="32"
                    y2="35"
                    stroke="#facc15"
                    strokeWidth="2.5"
                    strokeLinecap="round"
                />
            </svg>
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
