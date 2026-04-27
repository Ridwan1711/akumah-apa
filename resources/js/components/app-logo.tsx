import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-10 items-center justify-center rounded-xl bg-linear-to-br from-sidebar-primary to-sky-500 text-sidebar-primary-foreground shadow-[0_4px_20px_rgba(37,99,235,0.25)]">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="mb-0.5 truncate text-sm leading-tight font-bold text-sidebar-foreground">
                    Manhood Panel
                </span>
                <span className="truncate text-[11px] text-sidebar-foreground/70">
                    Pesantren Management
                </span>
            </div>
        </>
    );
}
