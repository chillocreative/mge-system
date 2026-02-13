export default function LoadingSpinner({ fullScreen = false, size = 'md' }) {
    const sizes = {
        sm: 'h-5 w-5',
        md: 'h-8 w-8',
        lg: 'h-12 w-12',
    };

    const spinner = (
        <div
            className={`${sizes[size]} animate-spin rounded-full border-2 border-gray-300 border-t-primary-600`}
        />
    );

    if (fullScreen) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-gray-50">
                {spinner}
            </div>
        );
    }

    return (
        <div className="flex items-center justify-center py-12">
            {spinner}
        </div>
    );
}
