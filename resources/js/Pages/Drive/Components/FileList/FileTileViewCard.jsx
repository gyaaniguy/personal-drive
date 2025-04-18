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
                                                                  setFileToRename
                                                              }) {
        const selectedFileSet = new Set([file.id]);
        let imageSrc = '/fetch-thumb/' + file.id;
        imageSrc += slug ? '/' + slug : ''

        return (
            <div
                className={`group relative overflow-hidden rounded-lg border border-gray-800 bg-gray-900/50 p-1 md:p-3 transition-all duration-200 hover:border-gray-700 hover:shadow-lg w-[180px]  sm:w-[210px] md:w-[270px] lg:w-[250px] flex flex-col justify-between  ${isSelected ? 'bg-gray-950' : ''} `}
            >

                <div className="">
                    {/* Filename and Checkbox Header */}
                    <div className="flex items-center justify-between relative">
                        <h3 className=" font-medium truncate max-w-[120px] sm:max-w-[160px] md:max-w-[200px] text-sm text-gray-400 mb-3 mt-1 overflow-hidden"
                            title={(isSearch ? file.public_path + '/' : '') + file.filename}>
                            {(isSearch ? file.public_path + '/' : '') + file.filename}
                        </h3>
                        <div
                            className="hover:bg-gray-600 p-2 pb-3 pl-3 cursor-pointer absolute -right-2 -top-2"
                            onClick={() => handlerSelectFile(file)}
                        >
                            <input
                                type="checkbox"
                                checked={isSelected}
                                className="h-4 w-4 rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-900"
                                onChange={() => {
                                }}
                            />
                        </div>
                    </div>

                    {/* File Icon */}
                    {file.is_dir === 0 &&
                        <div
                            className="flex cursor-pointer justify-center items-center transition-transform duration-200 h-[220px]"
                            onClick={() => handleFileClick(file)}
                        >
                            {file.has_thumbnail && !file.filename.endsWith('.svg') ? (
                                    <img
                                        src={imageSrc}
                                        alt="Thumbnail"
                                    />
                                )
                                : (
                                    <File
                                        className="text-gray-400 group-hover:text-gray-300 "
                                        size={120}
                                    />
                                )
                            }
                        </div>
                    }

                    {file.is_dir === 1 &&
                        <div
                            className="flex justify-center pb-3 transition-transform duration-200"
                        >
                            <Link
                                href={(isSearch ? '/drive/' + (file.public_path ? file.public_path + '/' : '') : path + '/') + file.filename}
                                className={`flex items-center  cursor-pointer h-[220px] w-[220px] justify-center`}
                                preserveScroll
                            >
                                <Folder className={`mr-2 text-yellow-600`} size={120}/>
                            </Link>
                        </div>
                    }
                </div>

                {/* Action Buttons */}
                <div
                    className="justify-between absolute bottom-0 left-1/2 transform -translate-x-1/2 w-full px-1 md:px-3 mb-1 md:mb-2 opacity-70 group-hover:flex hidden ">
                    {isAdmin &&
                        <div className="flex-1">
                            <DeleteButton
                                classes=" bg-red-500/10 hover:bg-red-500/20 text-red-500 py-2 rounded-md transition-colors duration-200  "
                                selectedFiles={selectedFileSet} setSelectedFiles={setSelectedFiles}/>
                        </div>
                    }
                    <div className="flex-1 flex ">
                        {isAdmin && (<><ShowShareModalButton classes="hidden group-hover:flex ml-1 md:ml-2  z-10"
                                                             setIsShareModalOpen={setIsShareModalOpen}
                                                             setFilesToShare={setFilesToShare}
                                                             filesToShare={new Set([file.id])}/>
                            <RenameModalButton classes="hidden group-hover:flex ml-1 md:ml-2 z-10"
                                               setIsRenameModalOpen={setIsRenameModalOpen}
                                               setFileToRename={setFileToRename}
                                               fileToRename={file}/> </>)
                        }
                        <DownloadButton
                            classes="w-full ml-1 md:ml-2  justify-center hover:bg-green-950 text-center py-2 rounded-md "
                            selectedFiles={selectedFileSet}
                            token={token}
                            setStatusMessage={setStatusMessage}
                            setAlertStatus={setAlertStatus}
                            slug={slug}/>
                    </div>
                </div>
            </div>
        )
    }
);

export default FileTileViewCard;