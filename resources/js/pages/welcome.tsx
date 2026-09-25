import { type ReactNode, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { CcLogo, CcLogoInline as CcInline } from '@/components/cc-logo';
import { LanguageSwitcher } from '@/components/language-switcher';
import { dashboard, login } from '@/routes';
import { useTranslations } from '@/hooks/use-translations';
import type { Auth } from '@/types';

type Translator = (key: string) => string;

// Copy lives here as English source strings. Entries whose text wraps around the
// [cc]: wordmark take the translator and render it themselves.
const features: { title: string; desc: (t: Translator) => ReactNode }[] = [
    {
        title: 'No extra tool',
        desc: (t) =>
            t(
                'Nobody has to log in, learn anything or keep a tab open. One address in cc is the whole action.',
            ),
    },
    {
        title: 'No wiki maintenance',
        desc: (t) =>
            t(
                'Articles come out of answers that are given anyway. Nobody writes documentation afterwards.',
            ),
    },
    {
        title: 'No migration',
        desc: (t) => (
            <>
                {t('Your existing mail setup stays where it is.')} <CcInline />{' '}
                {t('works on one separate mailbox alongside it.')}
            </>
        ),
    },
    {
        title: 'Just far smarter',
        desc: (t) =>
            t(
                'The same question needs answering one more time. After that the knowledge base answers along.',
            ),
    },
];

const steps: {
    n: string;
    title: string;
    desc: (t: Translator) => ReactNode;
}[] = [
    {
        n: '01',
        title: 'Connect a separate mailbox',
        desc: (t) => (
            <>
                {t('You create one address and connect it to')} <CcInline />{' '}
                {t('as a fixed recipient. Your own inbox stays out of it.')}
            </>
        ),
    },
    {
        n: '02',
        title: 'Put that address in cc',
        desc: (t) =>
            t(
                'In every thread where knowledge passes by. Anyone who does not add it changes nothing about their work.',
            ),
    },
    {
        n: '03',
        title: 'Question and answer are captured',
        desc: (t) => (
            <>
                {t('From the thread')} <CcInline />{' '}
                {t(
                    'takes the question, the answer and the context, with the email as the source.',
                )}
            </>
        ),
    },
    {
        n: '04',
        title: 'After that it answers by itself',
        desc: (t) => (
            <>
                {t('If the same question comes back,')} <CcInline />{' '}
                {t(
                    'replies directly in the thread and points to where it came from.',
                )}
            </>
        ),
    },
];

const trustPoints: {
    bold: (t: Translator) => ReactNode;
    text: string;
}[] = [
    {
        bold: (t) => (
            <>
                {t('Only what is in')} <CcInline /> {t('itself.')}
            </>
        ),
        text: ' No access to the rest of your mailbox.',
    },
    {
        bold: (t) => t('The source stays visible.'),
        text: ' Every article points back to the thread it came from.',
    },
    {
        bold: (t) => t('You can switch it off at any time.'),
        text: ' Revoke the connection in your own admin console.',
    },
];

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [email, setEmail] = useState('');
    const [submitted, setSubmitted] = useState(false);
    const t = useTranslations();

    const handleSubmit = () => setSubmitted(/.+@.+\..+/.test(email));

    return (
        <>
            <Head
                title={t('[cc]: — Connect a mailbox, knowledge gets smarter')}
            />

            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    width: '100%',
                    boxSizing: 'border-box',
                    background: '#f8f6f1',
                    fontFamily:
                        '"Instrument Sans", Helvetica, Arial, sans-serif',
                    WebkitFontSmoothing: 'antialiased',
                    color: '#17140f',
                }}
            >
                {/* Nav */}
                <nav
                    style={{
                        width: '100%',
                        maxWidth: 1080,
                        margin: '0 auto',
                        padding: '22px 28px',
                        boxSizing: 'border-box',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: 24,
                    }}
                >
                    <CcLogo />
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 26,
                        }}
                    >
                        <a href="#hoe" className="cc-nav-link">
                            {t('How it works')}
                        </a>
                        <LanguageSwitcher />
                        {auth.user ? (
                            <Link href={dashboard()} className="cc-nav-link">
                                {t('Dashboard')}
                            </Link>
                        ) : (
                            <>
                                <Link href={login()} className="cc-nav-link">
                                    {t('Sign in')}
                                </Link>
                                <a href="#koppelen" className="cc-btn-nav">
                                    {t('Create account')}
                                </a>
                            </>
                        )}
                    </div>
                </nav>

                {/* Hero */}
                <div
                    style={{
                        width: '100%',
                        maxWidth: 1080,
                        margin: '0 auto',
                        padding: '64px 28px 72px',
                        boxSizing: 'border-box',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 36,
                    }}
                >
                    <div
                        style={{
                            fontSize: 12,
                            letterSpacing: '0.14em',
                            textTransform: 'uppercase',
                            color: '#6b655c',
                        }}
                    >
                        {t('Internal knowledge · email')}
                    </div>
                    <h1
                        style={{
                            margin: 0,
                            fontSize: 'clamp(38px, 6vw, 68px)',
                            fontWeight: 600,
                            letterSpacing: '-0.04em',
                            lineHeight: 1.02,
                            color: '#17140f',
                            maxWidth: '16ch',
                        }}
                    >
                        {t('Connect')} <CcInline />{' '}
                        {t('to a mailbox and knowledge gets smarter')}
                    </h1>
                    <p
                        style={{
                            margin: 0,
                            fontSize: 19,
                            lineHeight: 1.5,
                            color: '#5c5750',
                            maxWidth: '56ch',
                        }}
                    >
                        {t('Let knowledge flow through')} <CcInline />{' '}
                        {t(
                            'from individuals into a central AI knowledge base. No new process: just email, the way knowledge has been passed on for years. Only far smarter.',
                        )}
                    </p>
                    <div
                        style={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: 14,
                            alignItems: 'center',
                        }}
                    >
                        <a href="#koppelen" className="cc-btn-pink">
                            {t('Start with your own mailbox')}
                        </a>
                        <a href="#hoe" className="cc-btn-outline">
                            {t('See how it works')}
                        </a>
                        <div style={{ fontSize: 13, color: '#6b655c' }}>
                            {t('Try it free · ready in 2 minutes')}
                        </div>
                    </div>
                </div>

                {/* Email demo */}
                <div
                    style={{
                        width: '100%',
                        background: '#17140f',
                        display: 'flex',
                        justifyContent: 'center',
                    }}
                >
                    <div
                        style={{
                            width: '100%',
                            maxWidth: 1080,
                            margin: '0 auto',
                            padding: 28,
                            boxSizing: 'border-box',
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 14,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 12,
                                letterSpacing: '0.1em',
                                textTransform: 'uppercase',
                                color: '#8a8478',
                            }}
                        >
                            {t('This is what it looks like in your inbox')}
                        </div>
                        <div
                            style={{
                                background: '#211d17',
                                borderRadius: 12,
                                padding: 22,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 18,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    gap: 10,
                                    alignItems: 'center',
                                    fontSize: 13,
                                    color: '#8a8478',
                                }}
                            >
                                <span>{t('to: team-support@')}</span>
                                <span>cc:</span>
                                <span
                                    style={{
                                        background: '#2e2922',
                                        borderRadius: 6,
                                        padding: '4px 10px',
                                        color: '#f8f6f1',
                                    }}
                                >
                                    {t('separate-mailbox@yourdomain.com')}
                                </span>
                            </div>
                            <div
                                style={{
                                    fontSize: 17,
                                    color: '#f8f6f1',
                                    lineHeight: 1.5,
                                    maxWidth: '62ch',
                                }}
                            >
                                {t(
                                    '"Does anyone know whether we can share the SOC 2 report with prospects under NDA?"',
                                )}
                            </div>
                            <div
                                style={{
                                    borderTop: '1px solid #332e26',
                                    paddingTop: 18,
                                    display: 'flex',
                                    gap: 14,
                                    alignItems: 'flex-start',
                                }}
                            >
                                <div style={{ flexShrink: 0, marginTop: 3 }}>
                                    <CcLogo size={24} color="#f8f6f1" />
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 8,
                                    }}
                                >
                                    <div
                                        style={{
                                            fontSize: 16,
                                            color: '#f8f6f1',
                                            lineHeight: 1.5,
                                            maxWidth: '60ch',
                                        }}
                                    >
                                        {t(
                                            'Yes, under NDA that is allowed. Legal recorded this in March: report only via the portal, not as an attachment.',
                                        )}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 12,
                                            color: '#8a8478',
                                        }}
                                    >
                                        {t(
                                            'source: 2 earlier threads · captured in Security & compliance',
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Features */}
                <div
                    style={{
                        width: '100%',
                        maxWidth: 1080,
                        margin: '0 auto',
                        padding: '80px 28px 8px',
                        boxSizing: 'border-box',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 36,
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        <h2
                            style={{
                                margin: 0,
                                fontSize: 'clamp(26px, 3vw, 36px)',
                                fontWeight: 600,
                                letterSpacing: '-0.03em',
                                color: '#17140f',
                                maxWidth: '24ch',
                            }}
                        >
                            {t('Nothing changes about how you work')}
                        </h2>
                        <p
                            style={{
                                margin: 0,
                                fontSize: 17,
                                lineHeight: 1.5,
                                color: '#5c5750',
                                maxWidth: '56ch',
                            }}
                        >
                            {t(
                                'Knowledge has been passed on by email for years. The problem is not the channel, it is that everything afterwards is left behind in separate inboxes.',
                            )}
                        </p>
                    </div>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(230px, 1fr))',
                            gap: 28,
                        }}
                    >
                        {features.map(({ title, desc }) => (
                            <div
                                key={title}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: 17,
                                        fontWeight: 600,
                                        letterSpacing: '-0.02em',
                                        color: '#17140f',
                                    }}
                                >
                                    {t(title)}
                                </div>
                                <div
                                    style={{
                                        fontSize: 15,
                                        lineHeight: 1.55,
                                        color: '#5c5750',
                                    }}
                                >
                                    {desc(t)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* How it works */}
                <div
                    id="hoe"
                    style={{
                        width: '100%',
                        maxWidth: 1080,
                        margin: '0 auto',
                        padding: '80px 28px',
                        boxSizing: 'border-box',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 40,
                    }}
                >
                    <h2
                        style={{
                            margin: 0,
                            fontSize: 'clamp(26px, 3vw, 36px)',
                            fontWeight: 600,
                            letterSpacing: '-0.03em',
                            color: '#17140f',
                        }}
                    >
                        {t('How it works')}
                    </h2>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(205px, 1fr))',
                            gap: 28,
                        }}
                    >
                        {steps.map(({ n, title, desc }) => (
                            <div
                                key={n}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 10,
                                }}
                            >
                                <div style={{ fontSize: 13, color: '#17140f' }}>
                                    {n}
                                </div>
                                <div
                                    style={{
                                        fontSize: 19,
                                        fontWeight: 600,
                                        letterSpacing: '-0.02em',
                                        color: '#17140f',
                                    }}
                                >
                                    {t(title)}
                                </div>
                                <div
                                    style={{
                                        fontSize: 15,
                                        lineHeight: 1.55,
                                        color: '#5c5750',
                                    }}
                                >
                                    {desc(t)}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* CTA / Signup */}
                <div
                    id="koppelen"
                    style={{
                        width: '100%',
                        maxWidth: 1080,
                        margin: '0 auto',
                        padding: '40px 28px 80px',
                        boxSizing: 'border-box',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 28,
                    }}
                >
                    <div
                        style={{
                            background: '#17140f',
                            borderRadius: 18,
                            padding: 'clamp(32px, 6vw, 64px)',
                            width: '100%',
                            maxWidth: 760,
                            margin: '0 auto',
                            boxSizing: 'border-box',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            textAlign: 'center',
                            gap: 26,
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <h2
                                style={{
                                    margin: 0,
                                    fontSize: 'clamp(28px, 4vw, 44px)',
                                    fontWeight: 600,
                                    letterSpacing: '-0.035em',
                                    lineHeight: 1.05,
                                    color: '#f8f6f1',
                                    maxWidth: '22ch',
                                }}
                            >
                                {t('Create an account and put')} <CcInline />{' '}
                                {t('in your next thread')}
                            </h2>
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 17,
                                    lineHeight: 1.5,
                                    color: '#c4bfb5',
                                    maxWidth: '52ch',
                                }}
                            >
                                {t(
                                    'You start with your work email. During onboarding you create the separate mailbox and connect Google or Microsoft. After that all you have to do is cc.',
                                )}
                            </p>
                        </div>
                        <div
                            style={{
                                display: 'flex',
                                flexWrap: 'wrap',
                                gap: 10,
                                alignItems: 'center',
                                justifyContent: 'center',
                                width: '100%',
                                maxWidth: 620,
                            }}
                        >
                            <input
                                type="email"
                                placeholder={t('you@yourcompany.com')}
                                value={email}
                                onChange={(e) => {
                                    setEmail(e.target.value);
                                    setSubmitted(false);
                                }}
                                className="cc-input-dark"
                            />
                            <button
                                type="button"
                                onClick={handleSubmit}
                                className="cc-submit-btn"
                            >
                                {t('Create account')}
                            </button>
                        </div>
                        {submitted && (
                            <div style={{ fontSize: 15, color: '#ff5c95' }}>
                                {t(
                                    'Check your inbox: we are sending a link to connect your mailbox.',
                                )}
                            </div>
                        )}
                        <div
                            style={{
                                display: 'flex',
                                flexWrap: 'wrap',
                                gap: 14,
                                alignItems: 'center',
                                justifyContent: 'center',
                                borderTop: '1px solid #332e26',
                                paddingTop: 24,
                                width: '100%',
                            }}
                        >
                            <div style={{ fontSize: 14, color: '#c4bfb5' }}>
                                {t('Or sign up with')}
                            </div>
                            <a href="#koppelen" className="cc-btn-dark-outline">
                                Google Workspace
                            </a>
                            <a href="#koppelen" className="cc-btn-dark-outline">
                                Microsoft 365
                            </a>
                        </div>
                    </div>
                    <div
                        style={{
                            width: '100%',
                            maxWidth: 760,
                            margin: '0 auto',
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit, minmax(200px, 1fr))',
                            gap: 20,
                            borderTop: '1px solid #e4e0d6',
                            paddingTop: 24,
                        }}
                    >
                        {trustPoints.map(({ bold, text }, i) => (
                            <div
                                key={i}
                                style={{
                                    fontSize: 14,
                                    lineHeight: 1.55,
                                    color: '#5c5750',
                                }}
                            >
                                <span
                                    style={{
                                        color: '#17140f',
                                        fontWeight: 600,
                                    }}
                                >
                                    {bold(t)}
                                </span>
                                {t(text)}
                            </div>
                        ))}
                    </div>
                </div>

                {/* Footer */}
                <div
                    style={{
                        width: '100%',
                        borderTop: '1px solid #e4e0d6',
                        display: 'flex',
                        justifyContent: 'center',
                    }}
                >
                    <div
                        style={{
                            width: '100%',
                            maxWidth: 1080,
                            margin: '0 auto',
                            padding: 28,
                            boxSizing: 'border-box',
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: 18,
                            alignItems: 'center',
                            justifyContent: 'space-between',
                        }}
                    >
                        <CcLogo />
                        <div style={{ fontSize: 12, color: '#6b655c' }}>
                            getcc.ai
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
