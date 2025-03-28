import React, {useEffect} from 'react';
import SortIcon from "../../Svgs/SortIcon.jsx";
import FileTileViewCard from "@/Pages/Drive/Components/FileList/FileTileViewCard.jsx";
import useThumbnailGenerator from "@/Pages/Drive/Hooks/useThumbnailGenerator.jsx";


const TileViewOne = ({
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

    useEffect(() => {
        useThumbnailGenerator(filesCopy, path);
    }, []);

    function handleSortClick(e, key) {
        let sortedFiles = sortCol(filesCopy, key);
        setFilesCopy(sortedFiles);
    }

    return (
        <div className="w-full flex flex-col flex-wrap ">
            <div className=" text-center flex items-center gap-x-2 justify-between mb-6 text-sm text-gray-400 ">
                <div
                    className="text-center bg-gray-900/50 hover:bg-gray-700 hover:cursor-pointer flex items-center gap-x-2 p-3 "
                    onClick={(e) => handleSelectAllToggle(filesCopy)}>
                    <input className=" hover:cursor-pointer" type="checkbox" checked={selectAllToggle} readOnly/>
                    <label className=" hover:cursor-pointer">Select All</label>
                </div>
                <div className="hover:cursor-pointer flex items-center gap-x-2">
                    <label></label>
                    <button
                        className={`p-1 rounded-md bg-gray-700 hover:bg-gray-600  ${sortDetails.key === 'filename' ? 'bg-gray-900 border border-gray-500/80 text-blue-400' : ''}`}
                        onClick={(e) => handleSortClick(e, 'filename')}
                    >
                        Name <SortIcon classes={`${sortDetails.key === 'filename' ? 'text-blue-500' : 'gray'} `}/>


                    </button>
                    <button
                        className={`p-1 rounded-md bg-gray-700 hover:bg-gray-600  ${sortDetails.key === 'file_type' ? 'bg-gray-900 border border-gray-500/80  text-blue-400' : ''}`}
                        onClick={(e) => handleSortClick(e, 'file_type')}
                    >
                        Type <SortIcon classes={`${sortDetails.key === 'file_type' ? 'text-blue-500' : 'gray'} `}/>

                    </button>
                    <button
                        className={`p-1 rounded-md bg-gray-700 hover:bg-gray-600  ${sortDetails.key === 'size' ? 'bg-gray-900 border border-gray-500/80 text-blue-400' : ''}`}
                        onClick={(e) => handleSortClick(e, 'size')}
                    >
                        Size <SortIcon classes={`${sortDetails.key === 'size' ? 'text-blue-500' : 'gray'} `}/>

                    </button>
                </div>
            </div>
            <div className="w-full flex flex-wrap gap-1 sm:gap-3 md:gap-5">
                {filesCopy.map((file) => (
                    <FileTileViewCard
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

export default TileViewOne;