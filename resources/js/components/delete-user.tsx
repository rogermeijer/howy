import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const t = useTranslations();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="destructive"
                    className="h-10 self-start"
                    data-test="delete-user-button"
                >
                    {t('Delete account')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{t('Permanently delete account?')}</DialogTitle>
                <DialogDescription>
                    {t(
                        'Your account and all associated data — emails, interpretations and your knowledge base — will be permanently deleted. Enter your password to confirm.',
                    )}
                </DialogDescription>

                <Form
                    {...ProfileController.destroy.form()}
                    options={{ preserveScroll: true }}
                    onError={() => passwordInput.current?.focus()}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="password" className="sr-only">
                                    {t('Password')}
                                </Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref={passwordInput}
                                    placeholder={t('Your password')}
                                    autoComplete="current-password"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        variant="secondary"
                                        onClick={() => resetAndClearErrors()}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    asChild
                                >
                                    <button
                                        type="submit"
                                        data-test="confirm-delete-user-button"
                                    >
                                        {t('Yes, delete my account')}
                                    </button>
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
