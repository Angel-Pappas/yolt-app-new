import { useId } from 'react';
import type { SVGAttributes } from 'react';

/**
 * The app icon: the company's tree-and-network mark whose branch nodes trace a
 * rising chart line. Full colour (its own green tile), so it ignores
 * `fill`/`text-*` classes. Same artwork as `public/favicon.svg`.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const gradientId = useId();

    return (
        <svg
            {...props}
            viewBox="0 0 512 512"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#8DC63F" />
                    <stop offset="1" stopColor="#006837" />
                </linearGradient>
            </defs>
            <rect
                width="512"
                height="512"
                rx="112"
                fill={`url(#${gradientId})`}
            />
            <g transform="translate(-10 0)">
                <g
                    fill="none"
                    stroke="#fff"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    <path
                        d="M256 334 C222 306 160 304 118 278"
                        strokeWidth="16"
                    />
                    <path
                        d="M256 334 C238 292 214 250 196 208"
                        strokeWidth="16"
                    />
                    <path
                        d="M256 334 C266 300 276 266 284 236"
                        strokeWidth="16"
                    />
                    <path
                        d="M256 334 C330 316 382 240 384 140"
                        strokeWidth="16"
                    />
                    <path
                        d="M118 278 L196 208 L284 236 L384 140"
                        strokeWidth="14"
                    />
                    <path d="M384 140 L426 102" strokeWidth="14" />
                    <path d="M388 98 L430 98 L430 140" strokeWidth="16" />
                </g>
                <path
                    fill="#fff"
                    d="M238 334 Q256 318 274 334 L282 404 Q286 428 322 434 L190 434 Q226 428 230 404 Z"
                />
                <g fill="#fff">
                    <circle cx="118" cy="278" r="23" />
                    <circle cx="196" cy="208" r="23" />
                    <circle cx="284" cy="236" r="23" />
                    <circle cx="384" cy="140" r="26" />
                </g>
            </g>
        </svg>
    );
}
