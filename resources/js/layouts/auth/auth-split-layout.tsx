import { Link } from '@inertiajs/react';
import { HowyLogo } from '@/components/brand/howy-logo';
import { home } from '@/routes';
import { useTranslations } from '@/hooks/use-translations';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const t = useTranslations();
    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div
                className="relative hidden h-full flex-col p-10 lg:flex"
                style={{ background: '#17140f' }}
            >
                <Link href={home()} className="relative z-20 flex items-center">
                    <HowyLogo size={30} tone="ivory" />
                </Link>
                <div className="relative z-20 mt-auto">
                    <blockquote className="space-y-2">
                        <p className="text-lg" style={{ color: '#c4bfb5' }}>
                            {t(
                                '"Knowledge shared over email, and now finally findable."',
                            )}
                        </p>
                    </blockquote>
                </div>
            </div>
            <div className="w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link
                        href={home()}
                        className="relative z-20 flex items-center justify-center lg:hidden"
                    >
                        <HowyLogo size={28} />
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p
                            className="text-sm text-balance"
                            style={{ color: '#5c5750' }}
                        >
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
