import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SetupLayout from '@/layouts/setup-layout';
import { configureEcho } from '@laravel/echo-react';

// Live updates over Reverb, where it is set up. Echo connects on first use,
// so nothing reaches out while rendering on the server.
if (import.meta.env.VITE_REVERB_APP_KEY) {
    configureEcho({
        broadcaster: 'reverb',
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // Public pages that bring their own chrome.
            case name === 'welcome':
            case name.startsWith('legal/'):
            case name.startsWith('mail-links/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('setup/'):
                return SetupLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#2f6b1f',
    },
});
