import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Path fragment that also marks the entry active. Used by the backlog
     * link, whose own URL is a redirect: the pages it leads to live under
     * /projects/{id}/backlog, so no single prefix covers them without also
     * covering the project pages.
     */
    match?: string;
};
