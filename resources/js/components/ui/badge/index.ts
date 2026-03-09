import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        accent:
          "border-transparent bg-accent text-accent-foreground [a&]:hover:bg-accent/90",
        success:
          "border-transparent bg-green-500 text-white [a&]:hover:bg-green-500/90",
        warning:
          "border-transparent bg-amber-500 text-white [a&]:hover:bg-amber-500/90",
        error:
          "border-transparent bg-red-500 text-white [a&]:hover:bg-red-500/90",
        default:
          "border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive:
         "border-transparent bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
        outline:
          "text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
        blue:
          "border-transparent bg-blue-500 text-white [a&]:hover:bg-blue-500/90",
        purple:
          "border-transparent bg-purple-500 text-white [a&]:hover:bg-purple-500/90",
        yellow:
          "border-transparent bg-yellow-500 text-white [a&]:hover:bg-yellow-500/90",
        indigo:
          "border-transparent bg-indigo-500 text-white [a&]:hover:bg-indigo-500/90",
        info:
          "border-transparent bg-sky-500 text-white [a&]:hover:bg-sky-500/90",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
