import FileItem from "../FileItem.jsx";
import FolderItem from "../FolderItem.jsx";
import React from "react";

// Mirrors the old server-side getItemSizeText: KB/KB/MB/GB, matches FileSizeFormatter.
function formatBytes(bytes) {
    if (bytes === 0) return "";
    if (bytes < 1024) return "1 KB";
    const units = ["KB", "KB", "MB", "GB"];
    let i = 0;
    for (; bytes >= 1024; i++) bytes /= 1024;
    const rounded = i < 2 ? Math.round(bytes) : Math.round(bytes * 10) / 10;
    return `${rounded} ${units[i]}`;
}

const FileListRow = React.memo(function FileListRow({
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
    favoriteFileIds,
    onAddFavorite,
}) {
    const sizeText =
        file.size || file.is_dir ? formatBytes(file.size) : "0 KB";
    const [sizeValue, sizeUnit] = sizeText.split(" ");
    return (
        <tr className="group cursor-pointer hover:bg-gray-700">
            <td
                className="w-6 p-1 text-center hover:bg-gray-900 md:w-10"
                onClick={() => handlerSelectFile(file)}
            >
                <input
                    type="checkbox"
                    checked={!!isSelected}
                    onChange={() => {}}
                />
            </td>
            <td className="max-w-0 overflow-hidden p-0">
                {file.is_dir ? (
                    <FolderItem
                        file={file}
                        isSearch={isSearch}
                        token={token}
                        setStatusMessage={setStatusMessage}
                        setAlertStatus={setAlertStatus}
                        setIsShareModalOpen={setIsShareModalOpen}
                        setFilesToShare={setFilesToShare}
                        isAdmin={isAdmin}
                        path={path}
                        slug={slug}
                        setSelectedFiles={setSelectedFiles}
                        setIsRenameModalOpen={setIsRenameModalOpen}
                        setFileToRename={setFileToRename}
                        favoriteFileIds={favoriteFileIds}
                        onAddFavorite={onAddFavorite}
                    />
                ) : (
                    <FileItem
                        file={file}
                        isSearch={isSearch}
                        token={token}
                        setStatusMessage={setStatusMessage}
                        setAlertStatus={setAlertStatus}
                        handleFileClick={handleFileClick}
                        setIsShareModalOpen={setIsShareModalOpen}
                        setFilesToShare={setFilesToShare}
                        isAdmin={isAdmin}
                        path={path}
                        slug={slug}
                        setSelectedFiles={setSelectedFiles}
                        setIsRenameModalOpen={setIsRenameModalOpen}
                        setFileToRename={setFileToRename}
                        favoriteFileIds={favoriteFileIds}
                        onAddFavorite={onAddFavorite}
                    />
                )}
            </td>
            <td className="whitespace-nowrap p-1 text-right text-xs text-gray-400/60 sm:p-2 md:text-sm">
                <span className="sm:hidden">
                    {new Date(file.date * 1000).toLocaleDateString(undefined, {
                        month: "2-digit",
                        day: "2-digit",
                    })}
                </span>
                <span className="hidden sm:inline">
                    {new Date(file.date * 1000).toISOString().slice(0, 10)}
                </span>
            </td>
            <td className="hidden whitespace-nowrap p-1 text-right text-xs text-gray-400/80 sm:table-cell sm:p-2 md:text-sm">
                <span>{sizeValue}</span>
                {sizeUnit && (
                    <span
                        className={`ml-1 text-xs ${sizeUnit.toLowerCase() === "kb" ? "text-green-400/50" : "text-blue-400/70"}`}
                    >
                        {sizeUnit.toLowerCase()}
                    </span>
                )}
            </td>
            <td
                className={`whitespace-nowrap p-1 text-right text-xs sm:p-2 md:text-sm ${file.is_dir ? "text-yellow-500/50" : "text-gray-400/80"}`}
            >
                {file.file_type}
            </td>
        </tr>
    );
});

export default FileListRow;
