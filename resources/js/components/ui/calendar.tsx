import {
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
} from 'lucide-react';
import * as React from 'react';
import {
    DayButton,
    DayPicker,
    getDefaultClassNames,
    type DayButtonProps,
} from 'react-day-picker';

import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import 'react-day-picker/style.css';
import '../../../css/react-day-picker-theme.css';

function Calendar({
    className,
    classNames,
    showOutsideDays = true,
    captionLayout = 'label',
    buttonVariant = 'ghost',
    formatters,
    components,
    ...props
}: React.ComponentProps<typeof DayPicker> & {
    buttonVariant?: React.ComponentProps<typeof Button>['variant'];
}) {
    const defaultClassNames = getDefaultClassNames();

    return (
        <DayPicker
            showOutsideDays={showOutsideDays}
            className={cn(
                'rounded-[20px] bg-background group/calendar p-3 [--cell-size:2.25rem] [[data-slot=popover-content]_&]:bg-transparent',
                '[--rdp-accent-color:var(--color-cc-accent-deep)] [--rdp-accent-background-color:var(--color-cc-accent-tint)] [--rdp-today-color:var(--color-cc-accent-deep)] [--rdp-selected-border:2px_solid_transparent]',
                className,
            )}
            captionLayout={captionLayout}
            formatters={{
                formatMonthDropdown: (date) =>
                    date.toLocaleString('default', { month: 'short' }),
                ...formatters,
            }}
            classNames={{
                root: cn('w-fit', defaultClassNames.root),
                months: cn(
                    'relative flex flex-col gap-4 md:flex-row',
                    defaultClassNames.months,
                ),
                month: cn(
                    'flex w-full flex-col gap-4',
                    defaultClassNames.month,
                ),
                nav: cn(
                    'absolute inset-x-0 top-0 flex w-full items-center justify-between gap-1',
                    defaultClassNames.nav,
                ),
                button_previous: cn(
                    buttonVariants({ variant: buttonVariant }),
                    'size-[--cell-size] select-none p-0 aria-disabled:opacity-50',
                    defaultClassNames.button_previous,
                ),
                button_next: cn(
                    buttonVariants({ variant: buttonVariant }),
                    'size-[--cell-size] select-none p-0 aria-disabled:opacity-50',
                    defaultClassNames.button_next,
                ),
                month_caption: cn(
                    'flex h-[--cell-size] w-full items-center justify-center px-[--cell-size]',
                    defaultClassNames.month_caption,
                ),
                dropdowns: cn(
                    'flex h-[--cell-size] w-full items-center justify-center gap-1.5 text-sm font-medium',
                    defaultClassNames.dropdowns,
                ),
                dropdown_root: cn(
                    'relative rounded-full border border-input shadow-xs has-focus:border-ring has-focus:ring-[3px] has-focus:ring-ring/50',
                    defaultClassNames.dropdown_root,
                ),
                dropdown: cn(
                    'absolute inset-0 bg-popover opacity-0',
                    defaultClassNames.dropdown,
                ),
                caption_label: cn(
                    'select-none font-medium',
                    captionLayout === 'label'
                        ? 'text-sm'
                        : 'flex h-8 items-center gap-1 rounded-full pr-1 pl-2 text-sm [&>svg]:size-3.5 [&>svg]:text-muted-foreground',
                    defaultClassNames.caption_label,
                ),
                month_grid: cn(
                    'w-full border-collapse',
                    defaultClassNames.month_grid,
                ),
                weekdays: cn('flex', defaultClassNames.weekdays),
                weekday: cn(
                    'flex-1 select-none rounded-md text-[0.8rem] font-normal text-muted-foreground',
                    defaultClassNames.weekday,
                ),
                week: cn('mt-2 flex w-full', defaultClassNames.week),
                week_number_header: cn(
                    'w-[--cell-size] select-none',
                    defaultClassNames.week_number_header,
                ),
                week_number: cn(
                    'select-none text-[0.8rem] text-muted-foreground',
                    defaultClassNames.week_number,
                ),
                day: cn(
                    'group/day relative aspect-square h-full w-full p-0 text-center select-none',
                    defaultClassNames.day,
                ),
                range_start: cn(
                    'rounded-l-full',
                    defaultClassNames.range_start,
                ),
                range_middle: cn(
                    'rounded-none bg-cc-accent-tint',
                    defaultClassNames.range_middle,
                ),
                range_end: cn(
                    'rounded-r-full',
                    defaultClassNames.range_end,
                ),
                today: cn('rounded-full', defaultClassNames.today),
                outside: cn(
                    'text-muted-foreground aria-selected:text-muted-foreground',
                    defaultClassNames.outside,
                ),
                disabled: cn(
                    'text-muted-foreground opacity-50',
                    defaultClassNames.disabled,
                ),
                hidden: cn('invisible', defaultClassNames.hidden),
                ...classNames,
            }}
            components={{
                Root: ({ className: rootClassName, rootRef, ...rootProps }) => (
                    <div
                        data-slot="calendar"
                        ref={rootRef}
                        className={cn(rootClassName)}
                        {...rootProps}
                    />
                ),
                Chevron: ({ className: chevronClassName, orientation, ...chevronProps }) => {
                    if (orientation === 'left') {
                        return (
                            <ChevronLeftIcon
                                className={cn('size-4', chevronClassName)}
                                {...chevronProps}
                            />
                        );
                    }

                    if (orientation === 'right') {
                        return (
                            <ChevronRightIcon
                                className={cn('size-4', chevronClassName)}
                                {...chevronProps}
                            />
                        );
                    }

                    return (
                        <ChevronDownIcon
                            className={cn('size-4', chevronClassName)}
                            {...chevronProps}
                        />
                    );
                },
                DayButton: CalendarDayButton,
                ...components,
            }}
            {...props}
        />
    );
}

function CalendarDayButton({
    className,
    day,
    modifiers,
    ...props
}: DayButtonProps) {
    const defaultClassNames = getDefaultClassNames();

    const ref = React.useRef<HTMLButtonElement>(null);
    React.useEffect(() => {
        if (modifiers.focused) {
            ref.current?.focus();
        }
    }, [modifiers.focused]);

    const selected =
        modifiers.selected ||
        modifiers.range_start ||
        modifiers.range_end;

    return (
        <Button
            ref={ref}
            variant="ghost"
            size="icon"
            data-day={day.date.toLocaleDateString()}
            className={cn(
                'aspect-square h-auto w-full min-w-[--cell-size] rounded-full font-normal leading-none shadow-none',
                selected &&
                    'bg-cc-accent font-semibold text-cc-ink shadow-xs hover:bg-cc-accent-hover hover:text-cc-ink',
                modifiers.range_middle &&
                    'rounded-none bg-cc-accent-tint text-cc-accent-deep hover:bg-cc-accent-tint hover:text-cc-accent-deep',
                !selected &&
                    modifiers.today &&
                    'font-semibold text-cc-accent-deep ring-1 ring-inset ring-cc-border-strong hover:bg-cc-raised hover:text-cc-ink',
                !selected &&
                    !modifiers.today &&
                    'text-cc-ink hover:bg-cc-raised hover:text-cc-ink',
                defaultClassNames.day,
                className,
            )}
            {...props}
        />
    );
}

export { Calendar, CalendarDayButton };
