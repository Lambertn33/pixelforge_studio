export interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginator {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    links: PageLink[];
}
