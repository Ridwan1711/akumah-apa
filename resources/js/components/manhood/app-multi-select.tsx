import type { Props as SelectProps } from 'react-select';
import Select from 'react-select';
import type { SelectOption } from './app-select';
import { manhoodReactSelectStyles } from './react-select-styles';

export type AppMultiSelectProps = Omit<SelectProps<SelectOption, true>, 'styles' | 'isMulti'> & {
    styles?: SelectProps<SelectOption, true>['styles'];
};

/** Searchable multi select — tags, many-to-many, bulk filters. */
export function AppMultiSelect({ styles, ...props }: AppMultiSelectProps) {
    const menuPortalTarget = typeof window !== 'undefined' ? document.body : undefined;

    return (
        <Select<SelectOption, true>
            isMulti
            isClearable
            isSearchable
            closeMenuOnSelect={false}
            menuPortalTarget={menuPortalTarget}
            menuPosition="fixed"
            styles={{ ...manhoodReactSelectStyles<SelectOption, true>(), ...styles }}
            {...props}
        />
    );
}
