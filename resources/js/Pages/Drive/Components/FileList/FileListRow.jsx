import FileItem from "../FileItem.jsx";
import FolderItem from "../FolderItem.jsx";
import React from "react";

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
}) {
    return (
        <div className="cursor-pointer hover:bg-gray-700 group flex flex-row w-full">
            <div
                className="p-1 md:px-6 w-6 md:w-10 flex  justify-center items-center hover:bg-gray-900"
                onClick={() => handlerSelectFile(file)}
            >
                <input
                    type="checkbox"
                    checked={!!isSelected}
                    onChange={() => {}}
                />
            </div>
            <div className="w-full overflow-hidden">
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
                    />
                )}
            </div>
            <div className="p-1 sm:p-2 md:p-4 text-right w-28 md:w-44 text-gray-400 text-sm">{file.sizeText}</div>
            <div className="p-1 sm:p-2 md:p-4 text-right w-28 md:w-44 text-gray-400 text-sm">{file.file_type}</div>
        </div>
    );
});

export default FileListRow;
