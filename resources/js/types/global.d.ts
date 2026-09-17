import type { FinanceLookups } from '@/components/transactions/lookups';
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
            sidebarOpen: boolean;
            financeLookups: FinanceLookups;
            [key: string]: unknown;
        };
    }
}
