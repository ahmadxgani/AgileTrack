import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

type Props = SVGAttributes<SVGElement> & {
    /**
     * Class applied to the arrow stroke only, so the crossbar can take an
     * accent colour while the "A" keeps the surrounding text colour.
     */
    arrowClassName?: string;
};

/**
 * The AgileTrack mark: an "A" whose crossbar runs on into a forward arrow,
 * a sprint track heading to its goal. Drawn with strokes in currentColor,
 * so it inherits the text colour wherever it sits.
 */
export default function AppLogoIcon({
    className,
    arrowClassName,
    ...props
}: Props) {
    return (
        <svg
            viewBox="0 0 32 32"
            fill="none"
            stroke="currentColor"
            strokeWidth={3.4}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className={cn('shrink-0', className)}
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <path d="M6 25.5 15.5 6.5 25 25.5" />
            <g className={arrowClassName}>
                <path d="M10 19h17.5" />
                <path d="m24 15.5 3.5 3.5-3.5 3.5" />
            </g>
        </svg>
    );
}
