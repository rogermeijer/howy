import { Link } from '@inertiajs/react';
import { CcLogo } from '@/components/cc-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
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
                background: '#f8f6f1',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '24px',
                fontFamily: '"Instrument Sans", Helvetica, Arial, sans-serif',
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
                    <CcLogo size={32} color="#17140f" />
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
                                color: '#f8f6f1',
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
                                    color: '#8a8478',
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
    );
}
