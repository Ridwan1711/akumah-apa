import type { Props as SelectProps } from 'react-select';
import Select from 'react-select';
import { manhoodReactSelectStyles } from './react-select-styles';

export type SelectOption = { value: string | number; label: string; isDisabled?: boolean };

export type AppSelectProps = Omit<SelectProps<SelectOption, false>, 'styles' | 'isMulti'> & {
    /** Merged after built-in Manhood styles. */
    styles?: SelectProps<SelectOption, false>['styles'];
};

/** Searchable single select — use for long lists or async options. */
export function AppSelect({ styles, ...props }: AppSelectProps) {
    return (
        <Select<SelectOption, false>
            isClearable
            isSearchable
            styles={{ ...manhoodReactSelectStyles<SelectOption, false>(), ...styles }}
            {...props}
        />
    );
}
