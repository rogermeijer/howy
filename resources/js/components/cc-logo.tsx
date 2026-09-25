type LogoProps = {
    size?: number;
    color?: string;
};

export function CcLogo({ size = 30, color = '#17140f' }: LogoProps) {
    return (
        <div
            style={{
                fontFamily: "'Idiqlat', serif",
                fontWeight: 400,
                fontSize: size,
                lineHeight: 1,
                color,
            }}
        >
            [cc]<span style={{ color: '#ff2d78' }}>:</span>
        </div>
    );
}

export function CcLogoInline() {
    return (
        <span style={{ fontFamily: "'Idiqlat', serif", fontWeight: 400 }}>
            [cc]<span style={{ color: '#ff2d78' }}>:</span>
        </span>
    );
}
