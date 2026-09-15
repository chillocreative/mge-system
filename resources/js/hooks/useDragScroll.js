import { useRef, useEffect, useState, useCallback } from 'react';

/**
 * Hook for enabling click-and-drag to scroll horizontally-scrollable containers.
 * Attach the returned ref to a container with overflow-x-auto.
 *
 * Uses a callback ref (not useRef) so the attach effect re-runs when the DOM
 * node actually mounts — many callers render the container behind a loading
 * gate, so a plain useRef + effect-on-mount would fire while the node is
 * still null and never re-attach once the table appears.
 *
 * Usage:
 *   const dragScrollRef = useDragScroll();
 *   <div ref={dragScrollRef} className="overflow-x-auto cursor-grab active:cursor-grabbing">
 *     <table>...</table>
 *   </div>
 */
export default function useDragScroll() {
    const [node, setNode] = useState(null);
    const isDragging = useRef(false);
    const startX = useRef(0);
    const startScrollLeft = useRef(0);
    const movedPixels = useRef(0);
    const THRESHOLD = 5; // pixels before considering it a drag

    const refCallback = useCallback((el) => {
        setNode(el);
    }, []);

    useEffect(() => {
        const container = node;
        if (!container) return;

        const handleMouseDown = (e) => {
            const target = e.target;
            // Skip drag for interactive elements so normal clicks still work
            if (
                target.closest('a, button, input, select, textarea, [contenteditable], [tabindex="0"]')
            ) {
                return;
            }

            isDragging.current = true;
            startX.current = e.clientX;
            startScrollLeft.current = container.scrollLeft;
            movedPixels.current = 0;

            // Apply grabbing cursor styles directly to DOM (no re-render)
            container.style.cursor = 'grabbing';
            document.body.style.cursor = 'grabbing';
            document.body.style.userSelect = 'none';
        };

        const handleMouseMove = (e) => {
            if (!isDragging.current) return;

            const deltaX = e.clientX - startX.current;
            movedPixels.current = Math.max(movedPixels.current, Math.abs(deltaX));

            // Only start scrolling once the drag threshold is exceeded, so a
            // plain click doesn't get treated as a (no-op) drag.
            if (movedPixels.current >= THRESHOLD) {
                e.preventDefault?.();
                container.scrollLeft = startScrollLeft.current - deltaX;
            }
        };

        const handleMouseUp = () => {
            if (!isDragging.current) return;

            container.style.cursor = '';
            document.body.style.cursor = '';
            document.body.style.userSelect = '';

            isDragging.current = false;
            movedPixels.current = 0;
        };

        container.addEventListener('mousedown', handleMouseDown);
        document.addEventListener('mousemove', handleMouseMove);
        document.addEventListener('mouseup', handleMouseUp);

        return () => {
            container.removeEventListener('mousedown', handleMouseDown);
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
        };
    }, [node]);

    return refCallback;
}
