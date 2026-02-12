import { Link } from "@inertiajs/react";
import { ChevronRight, HomeIcon } from "lucide-react";

export default function Breadcrumb({ path, isAdmin }) {
    let rootLink = isAdmin ? "/drive" : "/shared";
    let links = [];
    if (path) {
        let pathArr = path.split("/");
        pathArr.shift();
        pathArr.shift();
        for (let link of pathArr) {
            rootLink += "/" + link;
            links.push({ name: link, href: rootLink });
        }
    }

    return (
        <>
            {links.length > 0 && (
                <nav aria-label="Breadcrumb" className="my-1 w-full text-xs md:text-sm">
                    <ol className="flex  h-10 pr-2 w-full ">
                        {isAdmin && (
                            <li className="flex items-center min-w-0 ">
                                <Link
                                    className="hover:bg-gray-600 p-2 rounded-md inline-flex w-auto bg-gray-700  active:bg-gray-900 "
                                    href="/drive"
                                    preserveScroll
                                >
                                    <HomeIcon
                                        className={`text-gray-400 inline`}
                                        size={22}
                                    />
                                    <span className={``}></span>
                                </Link>
                                {links.length > 0 && path && (
                                    <ChevronRight
                                        className="w-4 h-4 text-gray-400 mr-1 md:mx-2"
                                        aria-hidden="true"
                                    />
                                )}
                            </li>
                        )}
                        {links.map((link, index) => (
                            <li key={index} className="flex items-center min-w-0">
                                {index === links.length - 1 ? (
                                    <span
                                        className="text-gray-400 font-medium  whitespace-nowrap truncate"
                                        aria-current="page"
                                    >
                                        {link.name}
                                    </span>
                                ) : (
                                    <>
                                        <Link
                                            href={link.href}
                                            className="text-blue-400 hover:text-blue-300 transition-colors duration-200 truncate whitespace-nowrap"
                                            preserveScroll
                                        >
                                            {link.name}
                                        </Link>
                                        <ChevronRight
                                            className="w-4 h-4 text-gray-400 mr-1 md:mx-2"
                                            aria-hidden="true"
                                        />
                                    </>
                                )}
                            </li>
                        ))}
                    </ol>
                </nav>
            )}
        </>
    );
}
