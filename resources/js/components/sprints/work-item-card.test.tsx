import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it, vi } from 'vite-plus/test';

const page = vi.hoisted(() => ({ errors: {} as Record<string, string> }));

vi.mock('@inertiajs/react', () => ({
    router: { patch: vi.fn(), delete: vi.fn(), post: vi.fn() },
    usePage: () => ({ props: { errors: page.errors } }),
}));

vi.mock('@/routes/sprints/items', () => ({
    status: () => ({ url: '/status' }),
}));

const { default: WorkItemCard } = await import('./work-item-card');

const statuses = [
    { value: 'todo', label: 'To do' },
    { value: 'in_progress', label: 'In progress' },
];

const rejection = 'The sprint of this item is closed.';

function render(canMoveStatus: boolean) {
    return renderToStaticMarkup(
        <WorkItemCard
            card={{
                id: 7,
                title: 'Plan the work',
                priority_label: 'High',
                assignees: [],
                can_move_status: canMoveStatus,
            }}
            projectId={1}
            sprintId={2}
            statuses={statuses}
        />,
    );
}

function occurrences(markup: string, needle: string): number {
    return markup.split(needle).length - 1;
}

describe('WorkItemCard status rejection', () => {
    it('points a movable card at its own rejection', () => {
        page.errors = { 'items.7.status': rejection };
        const markup = render(true);

        expect(markup).toContain('aria-describedby="status-error-7"');
        expect(markup).toContain('aria-invalid="true"');
        expect(occurrences(markup, rejection)).toBe(1);
    });

    it('keeps the rejection visible when the refreshed card is read only', () => {
        page.errors = { 'items.7.status': rejection };
        const markup = render(false);

        // The control is gone because the sprint closed after the page
        // loaded, but the reason the move failed must still be on the card,
        // exactly once.
        expect(markup).not.toContain('aria-label="Move Plan the work"');
        expect(markup).toContain('id="status-error-7"');
        expect(occurrences(markup, rejection)).toBe(1);
    });

    it('shows nothing for another card', () => {
        page.errors = { 'items.8.status': rejection };
        const markup = render(true);

        expect(markup).not.toContain(rejection);
        expect(markup).not.toContain('aria-describedby');
    });
});
