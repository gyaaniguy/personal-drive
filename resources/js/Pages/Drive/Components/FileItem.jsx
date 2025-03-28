import {File} from 'lucide-react';
import DownloadButton from "./DownloadButton.jsx";
import DeleteButton from "@/Pages/Drive/Components/DeleteButton.jsx";
import React from 'react';
import ShowShareModalButton from "@/Pages/Drive/Components/Shares/ShowShareModalButton.jsx";
import RenameModalButton from "@/Pages/Drive/Components/Shares/RenameModalButton.jsx";


const FileItem = React.memo(function FileItem({
                                                  file,
                                                  isSearch,
                                                  token,
                                                  setStatusMessage,
                                                  setAlertStatus,
                                                  handleFileClick,
                                                  setIsShareModalOpen,
                                                  setFilesToShare,
                                                  isAdmin,
                                                  slug,
                                                  setSelectedFiles,
                                                  setIsRenameModalOpen,
                                                  setFileToRename
                                              }) {
    return (
        <div
            className={` flex items-center  hover:bg-gray-900 justify-between`} onClick={(e) => handleFileClick(file)}
        >
            <div className="flex p-4 ">
                <File className={`mr-2 text-gray-300 `} size={20}/>
                <span className="overflow-hidden text-ellipsis whitespace-nowrap max-w-2xl">
                    {(isSearch ? file.public_path + '/' : '') + file.filename}
                </span>
            </div>
            <div className="flex">
                {
                    isAdmin &&
                    <DeleteButton classes="hidden group-hover:block mr-2  z-10" selectedFiles={new Set([file.id])}
                                  setSelectedFiles={setSelectedFiles}/>
                }
                <DownloadButton classes="hidden group-hover:block mr-2" selectedFiles={new Set([file.id])}
                                token={token} setStatusMessage={setStatusMessage} slug={slug}
                                setAlertStatus={setAlertStatus}
                />
                {
                    isAdmin &&
                    (
                        <>
                            <ShowShareModalButton
                                classes="hidden group-hover:block mr-2 z-10"
                                setIsShareModalOpen={setIsShareModalOpen}
                                setFilesToShare={setFilesToShare}
                                filesToShare={new Set([file.id])}/>
                            <RenameModalButton
                                classes="hidden group-hover:block mr-2  z-10"
                                setIsRenameModalOpen={setIsRenameModalOpen}
                                setFileToRename={setFileToRename}
                                fileToRename={file}/>
                        </>
                    )
                }

            </div>
        </div>
    );
});

export default FileItem;