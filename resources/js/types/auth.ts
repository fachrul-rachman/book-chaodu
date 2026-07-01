export type User = {
    id: number;
    name: string;
    email: string;
    role: 'ADMIN' | 'CHECKER';
    is_active: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
};

export type Auth = {
    user: User | null;
};
