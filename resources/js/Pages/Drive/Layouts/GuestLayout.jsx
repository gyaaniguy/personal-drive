import ApplicationLogo from '@/Components/ApplicationLogo.jsx';
import {Link} from '@inertiajs/react';

export default function GuestLayout({children}) {
    return (
        <div className="flex min-h-screen flex-col items-center  pt-6 sm:justify-center sm:pt-0 bg-gray-900">
            <div>
                <Link href="/public">
                    <ApplicationLogo className="h-20 w-20 fill-current text-gray-500"/>
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg bg-gray-800">
                {children}
            </div>
        </div>
    );
}
