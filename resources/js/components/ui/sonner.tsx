import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner } from 'sonner';

type ToasterProps = React.ComponentProps<typeof Sonner>;

export function Toaster(props: ToasterProps) {
    const { resolvedAppearance } = useAppearance();

    return (
        <Sonner
            theme={resolvedAppearance}
            richColors
            closeButton
            position="top-right"
            {...props}
        />
    );
}
