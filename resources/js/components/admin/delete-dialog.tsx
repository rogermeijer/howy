import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    action: RouteFormDefinition<'post'>;
    trigger: string;
    title: string;
    description: ReactNode;
    /** When set, this has to be typed over before the delete goes through. */
    confirmName?: string;
};

/**
 * A destructive action behind a dialog, optionally gated on typing a name.
 */
export function DeleteDialog({
    action,
    trigger,
    title,
    description,
    confirmName,
}: Props) {
    const t = useTranslations();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive" className="h-10 self-start">
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form {...action} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {confirmName !== undefined && (
                                <div className="grid gap-2">
                                    <Label htmlFor="confirm_name">
                                        {t('Type :name to confirm', {
                                            name: confirmName,
                                        })}
                                    </Label>
                                    <Input
                                        id="confirm_name"
                                        name="confirm_name"
                                        autoComplete="off"
                                        className="h-11"
                                    />
                                    <InputError message={errors.confirm_name} />
                                </div>
                            )}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    disabled={processing}
                                >
                                    {trigger}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
