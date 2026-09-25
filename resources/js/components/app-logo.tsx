import { CcLogo } from '@/components/cc-logo';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <span
                    style={{
                        fontFamily: "'Idiqlat', serif",
                        fontWeight: 400,
                        fontSize: 13,
                        lineHeight: 1,
                        color: '#f8f6f1',
                        letterSpacing: '-0.03em',
                    }}
                >
                    cc
                    <span style={{ color: '#ff2d78' }}>:</span>
                </span>
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    <CcLogo size={16} />
                </span>
            </div>
        </>
    );
}
