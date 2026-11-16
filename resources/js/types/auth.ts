export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

export interface AuthProps {
    user: AuthUser | null;
}
