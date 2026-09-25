import { cn } from '@/lib/utils';

type Props = {
    value: number;
    className?: string;
};

export function Confidence({ value, className }: Props) {
    const low = value < 75;

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-cc-raised">
                <div
                    className={cn(
                        'h-full rounded-full',
                        low ? 'bg-cc-pending-fg' : 'bg-cc-ink',
                    )}
                    style={{ width: `${value}%` }}
                />
            </div>
            <span className="w-8 text-right text-[12px] font-semibold text-cc-ink tabular-nums">
                {value}%
            </span>
        </div>
    );
}
