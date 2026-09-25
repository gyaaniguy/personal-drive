import Header from "@/Pages/Drive/Layouts/Header.jsx";
import { router } from "@inertiajs/react";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";
import Button from "../Components/Generic/Button.jsx";
import { DeleteIcon, PauseIcon, PlayIcon } from "lucide-react";
import CopyShareLinkButton from "@/Pages/Drive/Components/Shares/CopyShareLinkButton.jsx";

export default function AllShares({ shares, totalShares }) {
    let shareRoot = window.location.origin + "/shared/";
    let directRoot = window.location.origin + "/download/";

    function handlePause(id) {
        router.post(
            "/share-pause",
            { id: id },
            {
                preserveState: true,
                preserveScroll: true,
                only: ["shares", "flash"],
            },
        );
    }

    function handleDelete(id) {
        router.post(
            "/share-delete",
            { id: id },
            {
                preserveState: true,
                preserveScroll: true,
                only: ["shares", "flash"],
            },
        );
    }

    return (
        <>
            <Header />
            <div className="p-1 md:p-4 space-y-4 max-w-7xl mx-auto text-gray-300  bg-gray-800 min-h-screen">
                <h2 className="text-center text-3xl my-6 mb-8 font-semibold sm:text-4xl sm:my-12 sm:mb-32">
                    All Live Shares ({totalShares})
                </h2>
                <main className="mx-auto max-w-7xl">
                    <AlertBox />
                    <div>
                        <table className="w-full text-left bg-blue-900/15">
                            <thead>
                                <tr className="border-spacing-y-10 text-gray-500 border-gray-700 border-t font-light text-sm">
                                    <th className="py-3 mb-6 px-4 border-b border-gray-700 hidden md:table-cell">
                                        Created
                                    </th>
                                    <th className="py-3 mb-6 px-1 sm:px-4 border-b border-gray-700">
                                        Details
                                    </th>
                                    <th className="py-3 mb-6 px-1 sm:px-4 border-b border-gray-700">
                                        Files
                                    </th>
                                    <th className="py-2 mb-6 px-4 border-b border-gray-700 hidden md:table-cell">
                                        Has Password
                                    </th>
                                    <th className="py-3 mb-6 px-4 border-b border-gray-700 hidden md:table-cell">
                                        Expiring on
                                    </th>
                                    <th className="py-3 mb-6 px-1 sm:px-4 border-b border-gray-700">
                                        Enabled
                                    </th>
                                    <th className="py-3 mb-6 px-1 sm:px-4 border-b border-gray-700">
                                        Delete
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="">
                                {shares.map((share) => {
                                    return (
                                        <tr
                                            key={share.id}
                                            className={` hover:bg-gray-700/20 ${share.enabled ? "" : "bg-red-800/50"} text-sm`}
                                        >
                                            <td className="hidden md:table-cell p-1 sm:p-2 md:p-4 text-gray-400/70 ">
                                                {new Date(
                                                    share.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="p-1 sm:p-2 md:p-4  flex gap-y-2 flex-col max-w-[500px] ">
                                                <div className="flex gap-10 items-center ">
                                                    <span className="break-all font-semibold text-sm sm:text-lg text-indigo-300">
                                                        {(share.direct
                                                            ? directRoot
                                                            : shareRoot) +
                                                            share.slug}
                                                    </span>
                                                    {share.direct && (
                                                        <span className="text-xs px-2 py-0.5 rounded bg-indigo-800 text-indigo-200">
                                                            Direct
                                                        </span>
                                                    )}
                                                </div>

                                                <div>
                                                    {share.shared_files
                                                        .slice(0, 2)
                                                        .map(
                                                            (file) =>
                                                                file.local_file
                                                                    .filename,
                                                        )
                                                        .join(" || ")}{" "}
                                                    {share.shared_files.length >
                                                    1
                                                        ? "..."
                                                        : ""}
                                                </div>
                                            </td>
                                            <td className="p-1 sm:p-2 md:p-4  ">
                                                <div className="flex items-center justify-center gap-1 ">
                                                    <div className="flex flex-col ">
                                                        <div className="text-xs text-gray-500 text-center ">
                                                            <span className="text-sm sm:text-lg text-gray-400 font-semibold">
                                                                {
                                                                    share
                                                                        .shared_files
                                                                        .length
                                                                }
                                                            </span>
                                                            {share.shared_files
                                                                .length > 1 &&
                                                                `files`}
                                                            {share.shared_files
                                                                .length <= 1 &&
                                                                `file`}
                                                        </div>
                                                        <span>
                                                            <CopyShareLinkButton
                                                                sharedLink={
                                                                    (share.direct
                                                                        ? directRoot
                                                                        : shareRoot) +
                                                                    share.slug
                                                                }
                                                            />
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-1 sm:p-2 md:p-4 hidden md:table-cell ">
                                                {share.password ? "Yes" : "No"}
                                            </td>
                                            <td className="p-1 sm:p-2 md:p-4 hidden md:table-cell">
                                                {share.expiry &&
                                                    share.expiry_time}
                                                {!share.expiry && "Never"}
                                            </td>
                                            <td className="p-1 sm:p-2 md:p-4 text-center align-middle">
                                                <Button
                                                    onClick={() => {
                                                        handlePause(share.id);
                                                    }}
                                                    classes={`inline-flex ${share.enabled ? " bg-blue-400/30" : "bg-blue-500/80"} hover:bg-blue-300/30 active:bg-blue-200/30 '} `}
                                                >
                                                    {share.enabled ? (
                                                        <span>
                                                            <PauseIcon className=" inline" />
                                                            <span className="hidden md:inline">
                                                                Pause
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span>
                                                            <PlayIcon className="text-green-200 inline" />
                                                            <span className="hidden md:inline">
                                                                Resume
                                                            </span>
                                                        </span>
                                                    )}
                                                </Button>
                                            </td>
                                            <td className="py-4 px-4 text-red-200 text-center align-middle">
                                                <Button
                                                    onClick={() => {
                                                        handleDelete(share.id);
                                                    }}
                                                    classes={`inline-flex bg-red-800 hover:bg-red-600 active:bg-red-700 '} `}
                                                >
                                                    <span>
                                                        <DeleteIcon className="text-red-300 inline" />
                                                        <span className="hidden md:inline">
                                                            {" "}
                                                            Delete
                                                        </span>
                                                    </span>
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </main>
            </div>
        </>
    );
}
