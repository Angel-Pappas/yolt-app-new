/**
 * How fast (px per frame) to scroll while something is dragged at height `y`
 * over a scroll area spanning `top`–`bottom`: nothing in the middle, faster the
 * deeper the pointer is into the edge band (`zone` px), full speed (`max`) at or
 * past the edge. Negative = up, positive = down.
 */
export function edgeScrollSpeed(
    y: number,
    top: number,
    bottom: number,
    zone = 80,
    max = 24,
): number {
    if (bottom - top <= 0) {
        return 0;
    }
    // A band never takes more than a third of a short area, so its middle stays still.
    const band = Math.min(zone, (bottom - top) / 3);
    if (y < top + band) {
        return -Math.ceil(max * Math.min(1, (top + band - y) / band));
    }
    if (y > bottom - band) {
        return Math.ceil(max * Math.min(1, (y - (bottom - band)) / band));
    }

    return 0;
}

/** The nearest ancestor that scrolls vertically, or null for the page itself. */
export function scrollParent(el: Element | null): HTMLElement | null {
    for (let node = el?.parentElement; node; node = node.parentElement) {
        const { overflowY } = getComputedStyle(node);
        if (
            (overflowY === 'auto' || overflowY === 'scroll') &&
            node.scrollHeight > node.clientHeight
        ) {
            return node;
        }
    }

    return null;
}
