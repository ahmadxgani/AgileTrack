import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    /** Tile size in Tailwind spacing units; the wordmark scales with it. */
    size?: 'sm' | 'md' | 'lg';
};

const tile = {
    sm: 'size-8 rounded-lg [&>svg]:size-5',
    md: 'size-10 rounded-xl [&>svg]:size-6',
    lg: 'size-12 rounded-2xl [&>svg]:size-7',
};

const word = {
    sm: 'text-base',
    md: 'text-lg',
    lg: 'text-2xl',
};

/**
 * Brand lockup: the mark on a primary tile beside the application name.
 * The name comes from the shared page props, so it follows APP_NAME.
 */
export default function AppLogo({ className, size = 'sm' }: Props) {
    const { name } = usePage().props;

    return (
        <span className={cn('flex min-w-0 items-center gap-2.5', className)}>
            <span
                className={cn(
                    'flex shrink-0 items-center justify-center bg-primary text-primary-foreground shadow-sm shadow-primary/30',
                    tile[size],
                )}
            >
                <AppLogoIcon />
            </span>
            <span
                className={cn(
                    'truncate font-display font-bold tracking-tight',
                    word[size],
                )}
            >
                {name}
            </span>
        </span>
    );
}
