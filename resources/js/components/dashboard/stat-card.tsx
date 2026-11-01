import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: number;
    hint: string;
    icon: LucideIcon;
    tone?: 'default' | 'primary' | 'accent';
};

const tones = {
    default: 'bg-muted text-muted-foreground',
    primary: 'bg-primary/10 text-primary',
    accent: 'bg-brand-accent/25 text-brand-accent-foreground',
};

export default function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
}: Props) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-2xl border bg-card p-5 shadow-xs">
            <div className="min-w-0">
                <p className="text-sm font-medium text-muted-foreground">
                    {label}
                </p>
                <p className="mt-1 font-display text-3xl font-bold tracking-tight tabular-nums">
                    {value}
                </p>
                <p className="mt-1 text-xs text-pretty text-muted-foreground">
                    {hint}
                </p>
            </div>
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl',
                    tones[tone],
                )}
            >
                <Icon className="size-5" />
            </span>
        </div>
    );
}
