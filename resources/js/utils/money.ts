export const formatCost = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return `$${value}`;
};
