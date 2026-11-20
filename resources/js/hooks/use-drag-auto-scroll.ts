import { useEffect, useRef } from 'react';
import { edgeScrollSpeed, scrollParent } from '@/lib/drag-scroll';

/**
 * While a native drag is on, scroll the list it started in (or the page) when
 * the pointer nears the list's top or bottom edge, so a far-away row can be
 * reached. Browsers barely do this on their own inside a scroll box.
 *
 * Call `start(e.currentTarget)` from `onDragStart`. It stops on drop/dragend,
 * and also once the browser stops sending `dragover` (it sends one every few
 * dozen ms while a drag lasts) or the mouse moves normally again: when the list
 * redraws mid-drag, the dragend fires on a detached node and never reaches us.
 */
export function useDragAutoScroll(): (source: Element) => void {
    const stopRef = useRef<(() => void) | null>(null);

    useEffect(() => () => stopRef.current?.(), []);

    return (source: Element) => {
        stopRef.current?.();
        const box = scrollParent(source);
        let y: number | null = null;
        let lastOver = performance.now();
        let frame = 0;

        const onOver = (e: DragEvent) => {
            y = e.clientY;
            lastOver = performance.now();
        };
        const tick = () => {
            if (performance.now() - lastOver > 1000) {
                stop();
                return;
            }
            if (y !== null) {
                const rect = box?.getBoundingClientRect();
                const speed = edgeScrollSpeed(
                    y,
                    rect ? Math.max(rect.top, 0) : 0,
                    rect
                        ? Math.min(rect.bottom, window.innerHeight)
                        : window.innerHeight,
                );
                if (speed !== 0) {
                    (box ?? window).scrollBy(0, speed);
                }
            }
            frame = requestAnimationFrame(tick);
        };
        const stop = () => {
            cancelAnimationFrame(frame);
            document.removeEventListener('dragover', onOver);
            document.removeEventListener('dragend', stop, true);
            document.removeEventListener('drop', stop, true);
            document.removeEventListener('mousemove', stop, true);
            stopRef.current = null;
        };

        document.addEventListener('dragover', onOver);
        document.addEventListener('dragend', stop, true);
        document.addEventListener('drop', stop, true);
        document.addEventListener('mousemove', stop, true);
        frame = requestAnimationFrame(tick);
        stopRef.current = stop;
    };
}
