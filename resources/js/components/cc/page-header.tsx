import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: ReactNode;
    breadcrumb?: ReactNode;
    actions?: ReactNode;
};

export function PageHeader({ title, description, breadcrumb, actions }: Props) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="flex flex-col gap-2">
                {breadcrumb}
                <h1 className="cc-title">{title}</h1>
                {description && (
                    <div className="cc-body text-cc-subtle">{description}</div>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2.5">
                    {actions}
                </div>
            )}
        </div>
    );
}
