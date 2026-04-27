import ManhoodLayout from '@/layouts/manhood-layout';
import type { AppLayoutProps } from '@/types';

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => (
    <ManhoodLayout breadcrumbs={breadcrumbs} {...props}>
        {children}
    </ManhoodLayout>
);
