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
            /** Mails waiting for someone to review what they would add. */
            inbox: { needsReview: number };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
