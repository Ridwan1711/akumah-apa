import type { GroupBase, StylesConfig } from 'react-select';

/** Shared react-select styles using Manhood / shadcn CSS variables (see resources/css/app.css). */
export function manhoodReactSelectStyles<
    Option,
    IsMulti extends boolean = false,
    Group extends GroupBase<Option> = GroupBase<Option>,
>(): StylesConfig<Option, IsMulti, Group> {
    return {
        control: (base, state) => ({
            ...base,
            minHeight: '2.5rem',
            borderRadius: 'var(--radius)',
            borderColor: state.isFocused ? 'var(--ring)' : 'var(--border)',
            backgroundColor: 'var(--card)',
            boxShadow: state.isFocused ? '0 0 0 1px var(--ring)' : 'none',
            '&:hover': { borderColor: 'var(--ring)' },
        }),
        menu: (base) => ({
            ...base,
            borderRadius: 'var(--radius)',
            border: '1px solid var(--border)',
            backgroundColor: 'var(--popover)',
            boxShadow: '0 4px 20px rgb(0 0 0 / 8%), 0 1px 4px rgb(0 0 0 / 4%)',
            zIndex: 9999,
        }),
        menuPortal: (base) => ({
            ...base,
            zIndex: 9999,
        }),
        menuList: (base) => ({ ...base, padding: '4px' }),
        option: (base, state) => ({
            ...base,
            borderRadius: 'calc(var(--radius) - 4px)',
            fontSize: '0.875rem',
            backgroundColor: state.isSelected
                ? 'var(--primary)'
                : state.isFocused
                  ? 'var(--muted)'
                  : 'transparent',
            color: state.isSelected ? 'var(--primary-foreground)' : 'var(--foreground)',
            cursor: 'pointer',
        }),
        multiValue: (base) => ({
            ...base,
            borderRadius: 'var(--radius-sm)',
            backgroundColor: 'var(--primary)',
            opacity: 0.92,
        }),
        multiValueLabel: (base) => ({ ...base, color: 'var(--primary-foreground)' }),
        multiValueRemove: (base) => ({
            ...base,
            color: 'var(--primary-foreground)',
            ':hover': { backgroundColor: 'rgb(0 0 0 / 12%)', color: 'var(--primary-foreground)' },
        }),
        input: (base) => ({ ...base, color: 'var(--foreground)' }),
        singleValue: (base) => ({ ...base, color: 'var(--foreground)' }),
        placeholder: (base) => ({ ...base, color: 'var(--muted-foreground)' }),
        indicatorsContainer: (base) => ({ ...base, color: 'var(--muted-foreground)' }),
        clearIndicator: (base) => ({ ...base, color: 'var(--muted-foreground)' }),
        dropdownIndicator: (base) => ({ ...base, color: 'var(--muted-foreground)' }),
        loadingIndicator: (base) => ({ ...base, color: 'var(--primary)' }),
        noOptionsMessage: (base) => ({ ...base, color: 'var(--muted-foreground)', fontSize: '0.875rem' }),
        loadingMessage: (base) => ({ ...base, color: 'var(--muted-foreground)', fontSize: '0.875rem' }),
    };
}
