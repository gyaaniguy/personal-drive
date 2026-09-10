import { Folder } from "lucide-react";
import { Link } from "@inertiajs/react";
import DownloadButton from "./DownloadButton.jsx";
import DeleteButton from "@/Pages/Drive/Components/DeleteButton.jsx";
import FavoriteButton from "@/Pages/Drive/Components/FavoriteButton.jsx";
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
    favoriteFileIds,
    onAddFavorite,
}) {
    return (
        <div className="flex min-w-0 items-center justify-between md:hover:bg-gray-900">
            <Link
                href={
                    (isSearch
                        ? "/drive/" +
                          (file.public_path
                              ? file.public_path
                                    .split("/")
                                    .map(encodeURIComponent)
                                    .join("/") + "/"
                              : "")
                        : path.split("/").map(encodeURIComponent).join("/") +
                          "/") + encodeURIComponent(file.filename)
                }
                className={`min-w-0 flex-1 ${isSelected ? "bg-blue-100" : ""}`}
                preserveScroll
            >
                <div className="flex min-w-0 items-center p-1 sm:p-2">
                    <Folder
                        className={`mr-2 text-yellow-600 min-w-3 min-h-3 max-w-3 max-h-3`}
                    />
                    <span className="truncate">
                        {(isSearch ? file.public_path + "/" : "") +
                            file.filename}
                    </span>
                </div>
            </Link>

            <div className="hidden lg:flex gap-x-1">
                {isAdmin && (
                    <DeleteButton
                        classes="hidden group-hover:block mr-2  z-10"
                        selectedFiles={new Set([file.id])}
                        setSelectedFiles={setSelectedFiles}
                    />
                )}
                <DownloadButton
                    isAdmin={isAdmin}
                    classes="hidden group-hover:block mr-2  z-10"
                    selectedFiles={new Set([file.id])}
                    token={token}
                    setStatusMessage={setStatusMessage}
                    slug={slug}
                    setAlertStatus={setAlertStatus}
                />
                {isAdmin && (
                    <>
                        <FavoriteButton
                            isFavorite={favoriteFileIds.has(file.id)}
                            onClick={() => onAddFavorite(file.id)}
                            classes="hidden group-hover:flex group-focus-within:flex mr-2 z-10"
                        />
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
