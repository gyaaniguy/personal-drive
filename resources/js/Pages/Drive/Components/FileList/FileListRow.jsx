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
                className="p-2 md:px-6 w-20 items-center flex hover:bg-gray-900 justify-center"
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
            <div className="p-4 text-right w-44">{file.sizeText}</div>
            <div className="p-4 text-right w-44">{file.file_type}</div>
        </div>
    );
});

export default FileListRow;
