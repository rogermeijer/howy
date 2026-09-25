import { useTranslations } from '@/hooks/use-translations';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const t = useTranslations();

    // Pages declare their heading through a static `layout` object, which cannot
    // call a hook. They carry the English source strings and are translated here.
    return (
        <AuthLayoutTemplate
            title={title ? t(title) : ''}
            description={description ? t(description) : ''}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
