import React from "react";
import {File, Folder} from "lucide-react";
import {Link} from "@inertiajs/react";
import DeleteButton from "@/Pages/Drive/Components/DeleteButton.jsx";
import DownloadButton from "@/Pages/Drive/Components/DownloadButton.jsx";
import ShowShareModalButton from "@/Pages/Drive/Components/Shares/ShowShareModalButton.jsx";
import RenameModalButton from "@/Pages/Drive/Components/Shares/RenameModalButton.jsx";

const FileTileViewCard = React.memo(function FileTileViewCard({
    file,
    isSearch,
    token,
    setStatusMessage,
    setAlertStatus,
    handleFileClick,
    isSelected,
    handlerSelectFile,
    setIsShareModalOpen,
    setFilesToShare,
    isAdmin,
    path,
    slug,
    setSelectedFiles,
    setIsRenameModalOpen,
    setFileToRename,
}) {
    const selectedFileSet = new Set([file.id]);
    let imageSrc = "/fetch-thumb/" + file.id;
    imageSrc += slug ? "/" + slug : "";

    return (
        <div
            className={`group relative overflow-hidden rounded-lg border border-gray-800 bg-gray-900/50 px-2 md:px-3 p-1 md:p-3 transition-all duration-200 hover:border-gray-700 hover:shadow-lg min-h-[150px] md:min-h-[270px] flex flex-col h-full gap-2 pb-1 ${
                isSelected ? "bg-gray-950" : ""
            } `}
        >
            {/* Wrapper for Top Content (Text + Icon) */}
            <div className="flex flex-col gap-1 flex-1">
                {/* Filename and Checkbox Header */}
                <div className="flex items-start justify-between gap-2">
                    <h3
                        className="font-medium truncate max-w-[120px] md:max-w-[200px] text-sm text-gray-400 pb-0 overflow-hidden"
                        title={(isSearch ? file.public_path + "/" : "") + file.filename}
                    >
                        {(isSearch ? file.public_path + "/" : "") + file.filename}
                    </h3>
                    <div
                        className="hover:bg-gray-600 p-1 cursor-pointer flex items-center"
                        onClick={() => handlerSelectFile(file)}
                    >
                        <input
                            type="checkbox"
                            checked={isSelected}
                            className="h-3 w-3 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-900"
                            onChange={() => {}}
                        />
                    </div>
                </div>

                {/* Centered Icon Container */}
                <div className="flex-1 flex items-center justify-center min-h-0 w-full">
                    {file.is_dir === 0 ? (
                        <div
                            className="flex items-center justify-center w-full h-full cursor-pointer"
                            onClick={() => handleFileClick(file)}
                        >
                            {file.has_thumbnail && !file.filename.endsWith(".svg") ? (
                                <img
                                    src={imageSrc}
                                    alt="Thumbnail"
                                    className="object-contain max-h-full max-w-full"
                                />
                            ) : (
                                <File className="text-gray-400 group-hover:text-gray-300 w-24 h-24 md:w-40 md:h-40"/>
                            )}
                        </div>
                    ) : (
                        <Link
                            href={
                                (isSearch
                                    ? "/drive/" + (file.public_path ? file.public_path + "/" : "")
                                    : path + "/") + file.filename
                            }
                            className="flex items-center justify-center w-full h-full transition-transform duration-200"
                            preserveScroll
                        >
                            <Folder className="text-yellow-600 w-20 h-20 md:w-36 md:h-36"/>
                        </Link>
                    )}
                </div>
            </div>

            {/* Action Buttons - Hidden by default, shown on hover */}
            <div
                className="justify-between absolute bottom-0 left-1/2 transform -translate-x-1/2 w-full px-1 md:px-3 mb-1 md:mb-2 opacity-70 md:group-hover:flex hidden bg-gray-900/90 py-1">
                {isAdmin && (
                    <div className="flex-1">
                        <DeleteButton
                            classes="bg-red-500/10 hover:bg-red-500/20 text-red-500 py-2 rounded-md transition-colors duration-200 w-full"
                            selectedFiles={selectedFileSet}
                            setSelectedFiles={setSelectedFiles}
                        />
                    </div>
                )}
                <div className="flex-1 flex">
                    {isAdmin && (
                        <>
                            <ShowShareModalButton
                                classes="ml-1 md:ml-2 z-10"
                                setIsShareModalOpen={setIsShareModalOpen}
                                setFilesToShare={setFilesToShare}
                                filesToShare={new Set([file.id])}
                            />
                            <RenameModalButton
                                classes="ml-1 md:ml-2 z-10"
                                setIsRenameModalOpen={setIsRenameModalOpen}
                                setFileToRename={setFileToRename}
                                fileToRename={file}
                            />
                        </>
                    )}
                    <DownloadButton
                        isAdmin={isAdmin}
                        classes="w-full ml-1 md:ml-2 justify-center hover:bg-green-950 text-center py-2 rounded-md"
                        selectedFiles={selectedFileSet}
                        token={token}
                        setStatusMessage={setStatusMessage}
                        setAlertStatus={setAlertStatus}
                        slug={slug}
                    />
                </div>
            </div>
        </div>
    );
});

export default FileTileViewCard;
