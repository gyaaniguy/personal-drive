import { Folder } from "lucide-react";
import { Link } from "@inertiajs/react";
import DownloadButton from "./DownloadButton.jsx";
import DeleteButton from "@/Pages/Drive/Components/DeleteButton.jsx";
import React from "react";
import ShowShareModalButton from "@/Pages/Drive/Components/Shares/ShowShareModalButton.jsx";
import RenameModalButton from "@/Pages/Drive/Components/Shares/RenameModalButton.jsx";

const FolderItem = React.memo(function FolderItem({
    file,
    isSelected,
    isSearch,
    token,
    setStatusMessage,
    setAlertStatus,
    setIsShareModalOpen,
    setFilesToShare,
    isAdmin,
    path,
    slug,
    setSelectedFiles,
    setIsRenameModalOpen,
    setFileToRename,
}) {
    return (
        <div
            className={` flex items-center hover:bg-gray-900  justify-between`}
        >
            <Link
                href={
                    (isSearch
                        ? "/drive/" +
                          (file.public_path ? file.public_path + "/" : "")
                        : path + "/") + file.filename
                }
                className={`p-4  flex items-center w-full  ${isSelected ? "bg-blue-100" : ""}`}
                preserveScroll
            >
                <div className="flex  ">
                    <Folder className={`mr-2 text-yellow-600`} size={20} />
                    <span>
                        {(isSearch ? file.public_path + "/" : "") +
                            file.filename}
                    </span>
                </div>
            </Link>

            <div className="flex gap-x-1">
                {isAdmin && (
                    <DeleteButton
                        classes="hidden group-hover:block mr-2  z-10"
                        selectedFiles={new Set([file.id])}
                        setSelectedFiles={setSelectedFiles}
                    />
                )}
                <DownloadButton
                    classes="hidden  group-hover:block mr-2  z-10"
                    selectedFiles={new Set([file.id])}
                    token={token}
                    setStatusMessage={setStatusMessage}
                    slug={slug}
                    setAlertStatus={setAlertStatus}
                />
                {isAdmin && (
                    <>
                        <ShowShareModalButton
                            classes="hidden group-hover:block mr-2 z-10"
                            setIsShareModalOpen={setIsShareModalOpen}
                            setFilesToShare={setFilesToShare}
                            filesToShare={new Set([file.id])}
                        />
                        <RenameModalButton
                            classes="hidden group-hover:block mr-2  z-10"
                            setIsRenameModalOpen={setIsRenameModalOpen}
                            setFileToRename={setFileToRename}
                            fileToRename={file}
                        />
                    </>
                )}
            </div>
        </div>
    );
});
export default FolderItem;
