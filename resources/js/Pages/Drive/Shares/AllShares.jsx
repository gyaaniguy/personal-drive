import Header from "@/Pages/Drive/Layouts/Header.jsx";
import { router } from "@inertiajs/react";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";
import Button from "../Components/Generic/Button.jsx";
import { DeleteIcon, PauseIcon, PlayIcon } from "lucide-react";

export default function AllShares({ shares }) {
    function handlePause(id) {
        router.post(
            "/share-pause",
            { id: id },
            {
                preserveState: true,
                preserveScroll: true,
                only: ["shares", "flash"],
                onFinish: () => {},
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
                onFinish: () => {},
            },
        );
    }

    return (
        <>
            <Header />
            <div className="p-1 md:p-4 space-y-4 max-w-7xl mx-auto text-gray-300  bg-gray-800 min-h-screen">
                <h2 className="text-center text-4xl my-12 mb-32 font-semibold">
                    All Live Shares
                </h2>
                <main className="mx-auto max-w-7xl">
                    <AlertBox />
                    <div className="bg-blue-900/15">
                        <table className="w-full text-left  ">
                            <thead>
                                <tr className="border-spacing-y-10 text-gray-500 border-gray-700 border-t font-light text-sm">
                                    <th className="py-3 mb-6 px-4 border-b border-gray-700 hidden md:table-cell">
                                        Created
                                    </th>
                                    <th className="py-3 mb-6 px-4 border-b border-gray-700 ">
                                        Details
                                    </th>
                                    <th className="py-2 mb-6 px-4 border-b border-gray-700 hidden md:table-cell">
                                        Has Password
                                    </th>
                                    <th className="py-3 mb-6 px-4 border-b border-gray-700 hidden md:table-cell">
                                        Expiring on
                                    </th>
                                    <th className="py-3 mb-6 px-4 border-b border-gray-700 ">
                                        Enabled
                                    </th>
                                    <th className="py-3 mb-6 px-4 border-b border-gray-700 ">
                                        Delete
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="">
                                {shares.map((share) => (
                                    <tr
                                        key={share.id}
                                        className={` hover:bg-gray-700/20 ${share.enabled ? "" : "bg-red-800/50"} text-sm`}
                                    >
                                        <td className="hidden md:table-cell p-1 sm:p-2 md:p-4  ">
                                            {new Date(
                                                share.created_at,
                                            ).toLocaleDateString()}
                                        </td>
                                        <td className="p-1 sm:p-2 md:p-4  flex gap-y-2 flex-col max-w-[500px]">
                                            <div className="flex gap-10 items-center ">
                                                <span className="font-semibold text-sm sm:text-lg text-indigo-300">
                                                    {window.location.hostname +
                                                        "/shared/" +
                                                        share.slug}
                                                </span>
                                                <div className="flex items-center justify-center gap-1 hidden md:table-cell">
                                                    <span className="text-sm sm:text-lg text-gray-400 font-semibold">
                                                        {
                                                            share.shared_files
                                                                .length
                                                        }
                                                    </span>
                                                    <span className="text-sm text-gray-400 ">
                                                        {share.shared_files
                                                            .length > 1 &&
                                                            `files`}
                                                        {share.shared_files
                                                            .length <= 1 &&
                                                            `file`}
                                                    </span>
                                                </div>
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
                                                {share.shared_files.length > 1
                                                    ? "..."
                                                    : ""}
                                            </div>
                                        </td>
                                        <td className="p-1 sm:p-2 md:p-4 hidden md:table-cell ">
                                            {share.password ? "Yes" : "No"}
                                        </td>
                                        <td className="p-1 sm:p-2 md:p-4 hidden md:table-cell">
                                            {share.expiry && share.expiry_time}
                                            {!share.expiry && "Never"}
                                        </td>
                                        <td className="p-1 sm:p-2 md:p-4 ">
                                            <Button
                                                onClick={() => {
                                                    handlePause(share.id);
                                                }}
                                                classes={`inline-flex ${share.enabled ? " bg-blue-400/30" : "bg-blue-500/80"} hover:bg-blue-300/30 active:bg-blue-200/30 '} `}
                                            >
                                                {share.enabled ? (
                                                    <span>
                                                        <PauseIcon className=" inline" />
                                                        Pause
                                                    </span>
                                                ) : (
                                                    <span>
                                                        <PlayIcon className="text-green-200 inline" />{" "}
                                                        Resume{" "}
                                                    </span>
                                                )}
                                            </Button>
                                        </td>
                                        <td className="py-4 px-4 text-red-200">
                                            <Button
                                                onClick={() => {
                                                    handleDelete(share.id);
                                                }}
                                                classes={`inline-flex bg-red-800 hover:bg-red-600 active:bg-red-700 '} `}
                                            >
                                                <span>
                                                    <DeleteIcon className="text-red-300 inline" />{" "}
                                                    Delete{" "}
                                                </span>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </main>
            </div>
        </>
    );
}
