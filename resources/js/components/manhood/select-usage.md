# Select components — when to use what

## `AppSelect` / `AppMultiSelect` (`react-select`)

Use for:

- Long option lists where **search** helps
- **Async** loading of options
- **Multi** value (tags, many-to-many, filter chips)

Import from `@/components/manhood` (or `@/components/manhood/app-select`).

## `@/components/ui/select` (Radix)

Use for:

- Short lists (roughly **≤ 15** static options)
- Simple value changes without search
- Native-feel dropdown where bundle size matters

Always prefer **one** pattern per form: do not mix both for the same field type across the app without reason.
