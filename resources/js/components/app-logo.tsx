import { HowyAvatar } from '@/components/brand/howy-avatar';
import { HowyLogo } from '@/components/brand/howy-logo';

export default function AppLogo() {
    return (
        <>
            <HowyAvatar size={32} />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <HowyLogo size={16} />
            </div>
        </>
    );
}
