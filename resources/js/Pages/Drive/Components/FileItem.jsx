import {File} from 'lucide-react';
import DownloadButton from "./DownloadButton.jsx";
import DeleteButton from "@/Pages/Drive/Components/DeleteButton.jsx";
import React from 'react';
import ShowShareModalButton from "@/Pages/Drive/Components/Shares/ShowShareModalButton.jsx";


const FileItem = React.memo(function FileItem({ file, isSearch, token, setStatusMessage, handleFileClick, setIsShareModalOpen, setFilesToShare, isAdmin, path }) {
    return (
        <div
            className={` flex items-center  hover:bg-gray-900 justify-between`}
        >

            <div className="flex p-4 " onClick={(e) => handleFileClick(file)}>
                <File className={`mr-2 text-gray-300 `} size={20}/>
                <span className="overflow-hidden text-ellipsis whitespace-nowrap max-w-2xl">
                    {(isSearch ? file.public_path + '/' : '') + file.filename}
                </span>
            </div>
            <div className="flex ">
            {isAdmin && <DeleteButton classes="hidden group-hover:block mr-2  z-10" selectedFiles={new Set([file.id])}/> }

                <DownloadButton  classes="hidden group-hover:block mr-2" selectedFiles={new Set([file.id])}
                                token={token} setStatusMessage={setStatusMessage}
                />
             {isAdmin && <ShowShareModalButton  classes="hidden group-hover:block mr-2  z-10" setIsShareModalOpen={setIsShareModalOpen} setFilesToShare={setFilesToShare} filesToShare={new Set([file.id])}/> }

            </div>
        </div>
    );
});

export default FileItem;