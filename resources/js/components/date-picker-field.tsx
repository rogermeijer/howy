import { usePage } from '@inertiajs/react';
import { enUS, nl } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useFormatDate } from '@/hooks/use-format-date';
import { useTranslations } from '@/hooks/use-translations';
import { parseIsoDate, toIsoDate } from '@/lib/dates';
import { cn } from '@/lib/utils';
import type { LocalizationProps } from '@/types';

const locales = { en: enUS, nl } as const;

type Props = {
    id?: string;
    /** Submitted with its form as `Y-m-d`. */
    name?: string;
    defaultValue?: string;
    /** Outside a form: told the day picked as `Y-m-d`, or null once cleared. */
    onChange?: (value: string | null) => void;
    className?: string;
};

/**
 * A field that opens a calendar popover to pick the day: submitted as `Y-m-d`
 * with a form, or passed on as it changes, with a way to clear it.
 */
export function DatePickerField({
    id,
    name,
    defaultValue,
    onChange,
    className,
}: Props) {
    const t = useTranslations();
    const formatDate = useFormatDate();
    const { locale } = usePage<LocalizationProps>().props;
    const calendarLocale = locales[locale as keyof typeof locales] ?? enUS;

    const initial = defaultValue ? parseIsoDate(defaultValue) : undefined;
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<Date | undefined>(initial);
    const [iso, setIso] = useState(defaultValue ?? '');
    const [month, setMonth] = useState<Date>(() => initial ?? new Date());

    useEffect(() => {
        const next = defaultValue ? parseIsoDate(defaultValue) : undefined;
        setSelected(next);
        setIso(defaultValue ?? '');
        if (next) {
            setMonth(next);
        }
    }, [defaultValue]);

    const onOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);
        if (nextOpen) {
            setMonth(selected ?? new Date());
        }
    };

    const pick = (date: Date | undefined) => {
        setSelected(date);
        setIso(date ? toIsoDate(date) : '');
        onChange?.(date ? toIsoDate(date) : null);

        if (date) {
            setOpen(false);
        }
    };

    return (
        <>
            {name !== undefined && (
                <input type="hidden" name={name} value={iso} />
            )}
            <Popover open={open} onOpenChange={onOpenChange}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        id={id}
                        variant="outline"
                        className={cn(
                            'h-10 w-full justify-start rounded-full border-input bg-transparent px-4 text-left font-normal shadow-xs',
                            !selected && 'text-muted-foreground',
                            className,
                        )}
                    >
                        <CalendarIcon className="size-4 shrink-0 opacity-60" />
                        <span className="truncate">
                            {selected
                                ? formatDate(selected, { dateStyle: 'medium' })
                                : t('Pick a date')}
                        </span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-auto overflow-hidden rounded-[20px] border-cc-border p-0 shadow-md"
                    align="start"
                >
                    <Calendar
                        mode="single"
                        month={month}
                        onMonthChange={setMonth}
                        selected={selected}
                        onSelect={pick}
                        locale={calendarLocale}
                    />
                    {onChange !== undefined && selected && (
                        <div className="border-t border-cc-border px-3 py-2">
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-9 px-3 text-[13px] text-cc-subtle hover:text-cc-ink"
                                onClick={() => {
                                    pick(undefined);
                                    setOpen(false);
                                }}
                            >
                                {t('Clear')}
                            </Button>
                        </div>
                    )}
                </PopoverContent>
            </Popover>
        </>
    );
}
