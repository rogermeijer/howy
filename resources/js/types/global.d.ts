import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** Documents and mail statements waiting for approval. */
            knowledge: { awaitingApproval: number };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
