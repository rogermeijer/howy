import { Link } from '@inertiajs/react';
import { HowyLogo } from '@/components/brand/howy-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
import { PrivateBetaBar } from '@/components/marketing/private-beta';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div
            style={{
                minHeight: '100svh',
                background: '#f4f6f5',
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <PrivateBetaBar className="sticky top-0 z-40" />

            <div
                style={{
                    flex: 1,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: '24px',
                }}
            >
                <div
                    style={{
                        width: '100%',
                        maxWidth: 440,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: 24,
                    }}
                >
                    <Link href={home()}>
                        <HowyLogo size={32} />
                    </Link>

                    <div
                        className="cc-auth-card"
                        style={{
                            width: '100%',
                            borderRadius: 18,
                            padding: 'clamp(32px, 6vw, 48px)',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 24,
                            boxSizing: 'border-box',
                        }}
                    >
                        <div style={{ textAlign: 'center' }}>
                            <h1
                                style={{
                                    margin: 0,
                                    fontSize: '1.15rem',
                                    fontWeight: 600,
                                    letterSpacing: '-0.02em',
                                    color: '#f4f6f5',
                                    lineHeight: 1.3,
                                }}
                            >
                                {title}
                            </h1>
                            {description && (
                                <p
                                    style={{
                                        margin: '6px 0 0',
                                        fontSize: 14,
                                        color: '#84919a',
                                        lineHeight: 1.5,
                                    }}
                                >
                                    {description}
                                </p>
                            )}
                        </div>

                        {children}
                    </div>

                    <LanguageSwitcher />
                </div>
            </div>
        </div>
    );
}
