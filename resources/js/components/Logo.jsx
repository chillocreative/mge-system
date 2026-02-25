export default function Logo({ size = 40, showText = false, variant = 'dark', className = '' }) {
    const isLight = variant === 'light';
    const mainGreen = isLight ? '#bef264' : '#15803d';
    const ringColor = isLight ? '#bef264' : '#84cc16';

    return (
        <div className={`flex items-center gap-2.5 ${className}`}>
            <svg
                width={size}
                height={size}
                viewBox="0 0 40 40"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                {/* Circular ring */}
                <ellipse
                    cx="20"
                    cy="22"
                    rx="18"
                    ry="14"
                    stroke={ringColor}
                    strokeWidth="2.5"
                    className={isLight ? 'opacity-90' : 'opacity-60'}
                />
                {/* Three leaves */}
                <path
                    d="M12 24C12 20 9 17 9 17C9 17 6 20 6 24C6 28 9 29 9 29C9 29 12 28 12 24Z"
                    fill={mainGreen}
                />
                <path
                    d="M23 21C23 16 20 13 20 13C20 13 17 16 17 21C17 26 20 29 20 29C20 29 23 26 23 21Z"
                    fill={mainGreen}
                />
                <path
                    d="M34 24C34 20 31 17 31 17C31 17 28 20 28 24C28 28 31 29 31 29C31 29 34 28 34 24Z"
                    fill={mainGreen}
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
