import NavLink from '@/Components/NavLink.jsx';
import {Link} from "@inertiajs/react";
import SearchBar from "@/Pages/Drive/Components/SearchBar.jsx";
import useSearchUtil from '@/Pages/Drive/Hooks/useSearchUtil.jsx';

export default function Header({}) {
    const {handleSearch} = useSearchUtil();
    return (
        <div className="bg-gray-900">
            <nav className="bg-gray-800">
                <div className="mx-auto max-w-7xl pr-1 md:px-6 ">
                    <div className="flex h-16 justify-between ">
                        <div className="space-x-1 md:space-x-4 sm:space-x-8 sm:-my-px pr-1 sm:pr-3 flex">
                            <NavLink
                                href={route('drive')}
                            >
                                <img src="/img/logo.png" alt="Logo" className="hidden md:inline-block w-16  "/>
                            </NavLink>
                            <NavLink
                                href={route('drive')}
                                active={route().current('drive')}
                                className={` text-xs `}
                            >
                                Files
                            </NavLink>
                            <NavLink
                                href={route('admin-config')}
                                active={route().current('admin-config')}
                                className={` text-xs `}
                            >
                                Settings
                            </NavLink>
                            <NavLink
                                href={route('shares-all')}
                                active={route().current('shares-all')}
                                className={` text-xs `}
                            >
                                Shares
                            </NavLink>
                        </div>


                        <div className="flex gap-x-1 sm:gap-x-2 items-center">
                            <SearchBar handleSearch={handleSearch}/>

                            <div className="">
                                <div className="relative group text-gray-300">
                                    <button
                                        className="py-2.5 peer rounded font-semibold ">
                                        <svg className="w-4 h-4 md:w-6 md:h-6 rotate-90"
                                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                             stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                                  d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
                                        </svg>
                                    </button>
                                    <div
                                        className="absolute z-20 top-10 translate-x-[-80%] bg-gray-900 min-w-[80px]  peer-focus:visible peer-focus:opacity-100 opacity-0 invisible duration-200 p-1 border border-dimmed border-gray-800 text-center">
                                        <div
                                            className=" cursor-pointer  hover:text-link rounded-md">
                                            <Link
                                                method="post"
                                                href={route('logout')}
                                                as="button"
                                            >
                                                Log Out
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </nav>

        </div>
    );
}
