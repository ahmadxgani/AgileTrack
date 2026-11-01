import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';

export function AppContent({
    children,
    ...props
}: React.ComponentProps<typeof SidebarInset>) {
    return <SidebarInset {...props}>{children}</SidebarInset>;
}
