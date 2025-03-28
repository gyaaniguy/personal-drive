import React from 'react';

import FileListRow from "./FileListRow.jsx";
import { Link } from '@inertiajs/react';

import {useNavigate} from 'react-router-dom';
import SortIcon from "../../Svgs/SortIcon.jsx";


const ListView = ({
                      filesCopy,
                      token,
                      setStatusMessage,
                      setAlertStatus,
                      handleFileClick,
                      isSearch,
                      sortCol,
                      sortDetails,
                      setFilesCopy,
                      path,
                      selectedFiles,
                      handlerSelectFile,
                      selectAllToggle,
                      handleSelectAllToggle,
                      setIsShareModalOpen,
                      setFilesToShare,
                      isAdmin,
                      slug,
                      setSelectedFiles,
                      setIsRenameModalOpen,
                      setFileToRename
                  }) => {
    function handleSortClick(e, key) {
        let sortedFiles = sortCol(filesCopy, key);
        setFilesCopy(sortedFiles);
    }

    return (
        <div className="w-full">
            <hr className=" text-gray-500 border-gray-600"/>
            <div className="flex items-center justify-between text-gray-400 border-b border-b-gray-600 w-full">
                <div className="p-2 md:px-6 w-20 text-center hover:bg-gray-900 hover:cursor-pointer"
                     onClick={(e) => handleSelectAllToggle(filesCopy)}>
                    <input type="checkbox" checked={selectAllToggle} readOnly/>
                </div>
                <div onClick={(e) => handleSortClick(e, 'filename')}
                     className={`text-left w-full p-2 px-4 hover:bg-gray-900 hover:cursor-pointer ${sortDetails.key === 'filename' ? 'text-blue-400' : ''}`}>
                    <span>Name</span>
                    <SortIcon classes={`${sortDetails.key === 'filename' ? 'text-blue-500' : 'gray'} `}/>
                </div>
                <div onClick={(e) => handleSortClick(e, 'size')}
                     className={`p-2 px-4 w-44 hover:bg-gray-900  hover:cursor-pointer text-right ${sortDetails.key === 'size' ? 'text-blue-400' : ''}`}>
                    <span>Size</span>
                    <SortIcon classes={`${sortDetails.key === 'size' ? 'text-blue-500' : 'gray'} `}/>
                </div>
                <div onClick={(e) => handleSortClick(e, 'file_type')}
                     className={`p-2 px-4 w-44 hover:bg-gray-900  hover:cursor-pointer text-right ${sortDetails.key === 'file_type' ? 'text-blue-400' : ''}`}>
                    <span>Type</span>
                    <SortIcon classes={`${sortDetails.key === 'file_type' ? 'text-blue-500' : 'gray'} `}/>
                </div>
            </div>
            {(isSearch || (path && !path.match(/shared\/[A-Za-z0-9\-_]+$/) && path !== '/drive')) && (
                <div>
                    <Link className="cursor-pointer hover:bg-gray-700 p-4 px-8 w-full block" title="Go Up" href={path ? path.substring(0, path.lastIndexOf('/')) : `/drive`} >..</Link>
                </div>
            )}
            <div className=" flex flex-wrap text-sm sm:text-base">
                {filesCopy.map((file) => (
                    <FileListRow
                        key={file.id}
                        file={file}
                        isSearch={isSearch}
                        token={token}
                        setStatusMessage={setStatusMessage}
                        setAlertStatus={setAlertStatus}
                        handleFileClick={handleFileClick}
                        isSelected={selectedFiles.has(file.id)}
                        handlerSelectFile={handlerSelectFile}
                        setIsShareModalOpen={setIsShareModalOpen}
                        setFilesToShare={setFilesToShare}
                        isAdmin={isAdmin}
                        path={path}
                        slug={slug}
                        setSelectedFiles={setSelectedFiles}
                        setIsRenameModalOpen={setIsRenameModalOpen}
                        setFileToRename={setFileToRename}
                    />
                ))}
            </div>
        </div>
    );
};

export default ListView;