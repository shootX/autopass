"use client"

import * as React from "react"
import {
  ChevronDownIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from "lucide-react"
import { DayButton, DayPicker, getDefaultClassNames } from "react-day-picker"

import { cn } from "@/lib/utils"
import { Button, buttonVariants } from "@/components/ui/button"
function Calendar({
  className,
  classNames,
  showOutsideDays = true,
  buttonVariant = "ghost",
  captionLayout = "label",
  formatters,
  components,
  ...props
}: React.ComponentProps<typeof DayPicker> & {
  buttonVariant?: React.ComponentProps<typeof Button>["variant"]
}) {
  const defaultClassNames = getDefaultClassNames()

  return (
    <DayPicker
        disabled={
        props.disableBefore
          ? { before: new Date(new Date().setHours(0, 0, 0, 0)) }
          : props.disabled
      }
      showOutsideDays={showOutsideDays}
      formatters={{
        formatMonthDropdown: (date) =>
          date.toLocaleString("default", { month: "short" }),
        ...formatters,
      }}
      className={cn(
        "bg-background group/calendar p-3 [--cell-size:--spacing(8)]",
        String.raw`rtl:**:[.rdp-button_next>svg]:rotate-180`,
        String.raw`rtl:**:[.rdp-button_previous>svg]:rotate-180`,
        className
      )}
      classNames={{
        root: cn("w-fit", defaultClassNames.root),
        months: cn("flex gap-4 flex-col md:flex-row relative", defaultClassNames.months),
        month: cn(
          "grid grid-rows-[auto_1fr] w-full gap-4",
          defaultClassNames.month
        ),        
        month_caption: cn(
          "hidden",
          defaultClassNames.month_caption
        ),
        
        caption_label: cn(
          "hidden",
          defaultClassNames.caption_label
        ),
        nav: cn(
          "hidden",
          defaultClassNames.nav
        ),
        button_previous: cn(
          buttonVariants({ variant: buttonVariant }),
          "hidden",
          defaultClassNames.button_previous
        ),
        button_next: cn(
          buttonVariants({ variant: buttonVariant }),
          "hidden",
          defaultClassNames.button_next
        ),
        weekdays: cn("flex mb-2", defaultClassNames.weekdays),
        weekday: cn(
          "text-[#A2ABA4] font-normal text-[20px] leading-[23px] flex-1 text-center",
          defaultClassNames.weekday
        ),
        week: cn("flex w-full gap-y-2 mb-1", defaultClassNames.week),
        week_number_header: cn("select-none w-(--cell-size)", defaultClassNames.week_number_header),
        week_number: cn("text-[0.8rem] select-none text-muted-foreground", defaultClassNames.week_number),
        day: cn("relative w-full h-[40px] text-center select-none", defaultClassNames.day),

        range_start: cn("bg-[#CDF5D8] text-[#1E9E5A] rounded-full", defaultClassNames.range_start),
        range_middle: cn("text-[#1E9E5A]", defaultClassNames.range_middle),
        range_end: cn("bg-[#CDF5D8] text-[#1E9E5A] rounded-full", defaultClassNames.range_end),

        today: cn(
          "bg-accent text-accent-foreground rounded-md data-[selected=true]:rounded-none",
          defaultClassNames.today
        ),

        ...classNames,
      }}
      components={{
        Root: ({ className, rootRef, ...props }) => (
          <div data-slot="calendar" ref={rootRef} className={cn(className)} {...props} />
        ),
        Chevron: ({ className, orientation, ...props }) => {
          const size = 23
          if (orientation === "left") {
            return <ChevronLeftIcon size={size} className={cn(className)} {...props} />
          }
          if (orientation === "right") {
            return <ChevronRightIcon size={size} className={cn(className)} {...props} />
          }
          return <ChevronDownIcon size={size} className={cn(className)} {...props} />
        },
        DayButton: CalendarDayButton,
        WeekNumber: ({ children, ...props }) => (
          <td {...props}>
            <div className="flex size-(--cell-size) items-center justify-center text-center">
              {children}
            </div>
          </td>
        ),
        ...components,
      }}
      {...props}
    />
  )
}

function CalendarDayButton({
  className,
  day,
  modifiers,
  ...props
}: React.ComponentProps<typeof DayButton>) {
  const defaultClassNames = getDefaultClassNames()
  const ref = React.useRef<HTMLButtonElement>(null)

  React.useEffect(() => {
    if (modifiers.focused) ref.current?.focus()
  }, [modifiers.focused])

  return (
    <Button
      ref={ref}
      variant="ghost"
      size="icon"
      data-day={day.date.toLocaleDateString()}
      data-selected-single={
        modifiers.selected &&
        !modifiers.range_start &&
        !modifiers.range_end &&
        !modifiers.range_middle
      }
      data-range-start={modifiers.range_start}
      data-range-end={modifiers.range_end}
      data-range-middle={modifiers.range_middle}
      className={cn(
        "flex items-center aspect-square w-full h-full rounded-full text-[20px] leading-[23px] font-[Roboto] text-[#14482F]",
        modifiers.selected && "bg-[#CDF5D8] text-[#1E9E5A]",
        className
      )}
      {...props}
    />
  )
}

export { Calendar, CalendarDayButton }