import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';

type Props = {
    name: string;
    domain: string | null;
    errors: Partial<Record<string, string>>;
};

/**
 * The account's name and the domain its company mails from, shared by the
 * settings page and the first step of setup.
 */
export function AccountFields({ name, domain, errors }: Props) {
    const t = useTranslations();

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <div className="grid content-start gap-2">
                <Label htmlFor="name">{t('Account name')}</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={name}
                    required
                    autoComplete="organization"
                    className="h-11"
                />
                <p className="cc-caption">
                    {t('The name of your company or team.')}
                </p>
                <InputError message={errors.name} />
            </div>

            <div className="grid content-start gap-2">
                <Label htmlFor="domain">{t('Email domain')}</Label>
                <div className="relative">
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-y-0 left-4 flex items-center text-cc-subtle"
                    >
                        @
                    </span>
                    <Input
                        id="domain"
                        name="domain"
                        defaultValue={domain ?? ''}
                        placeholder={t('yourcompany.com')}
                        autoComplete="off"
                        autoCapitalize="none"
                        spellCheck={false}
                        className="h-11 pl-8"
                    />
                </div>
                <p className="cc-caption">
                    {t('The domain your company mails from.')}
                </p>
                <InputError message={errors.domain} />
            </div>
        </div>
    );
}
