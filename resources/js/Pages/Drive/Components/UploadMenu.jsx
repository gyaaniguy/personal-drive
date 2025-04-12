'use client'

import {useEffect, useRef, useState} from 'react'
import {router, usePage} from '@inertiajs/react'
import useClickOutside from "../Hooks/useClickOutside.jsx";
import CreateItemModal from './CreateFolderModal.jsx'
import useThumbnailGenerator from "@/Pages/Drive/Hooks/useThumbnailGenerator.jsx";
import {UploadCloudIcon} from "lucide-react";
import FileDropzone from "@/Pages/Drive/Components/DropZone.jsx";
import ReplaceAbortModal from "@/Pages/Drive/Components/ReplaceAbortModal.jsx";


const UploadMenu = ({path, setStatusMessage, files}) => {
    let {flash} = usePage().props;

    const [isMenuOpen, setIsMenuOpen] = useState(false)
    const [isReplaceAbortModalOpen, setIsReplaceAbortModalOpen] = useState(false)
    const [uploadedFiles, setUploadedFiles] = useState([]);

    const fileInputRef = useRef(null)
    const folderInputRef = useRef(null)
    const resetFileFolderInput = () => {
        if (fileInputRef.current) {
            fileInputRef.current.value = ''; // Clears the selected files
        }
        if (folderInputRef.current) {
            folderInputRef.current.value = ''; // Clears the selected files
        }
    };

    const menuRef = useRef(null);
    useClickOutside(menuRef, () => setIsMenuOpen(false));
    const [isModalOpen, setIsModalOpen] = useState(false);
    const isFile = useRef(false);

    function uploadFiles(selectedFileForUpload) {
        setStatusMessage('Uploading...');
        const formData = new FormData();
        selectedFileForUpload.forEach(file => {
            const fileName = file.webkitRelativePath || file.relativePath || file.name;
            formData.append('files[]', file, fileName);
        });
        formData.append('path', path);
        router.post('/upload', formData, {
            only: ['files', 'flash'],
            onSuccess: (response) => {
                setUploadedFiles(selectedFileForUpload);
            },
            onError: (error) => {
                if (error.response?.status === 413) {
                    setStatusMessage('File too large for server to handle. Please upload a smaller file.');
                }
            },
            onFinish: (response) => {
                setStatusMessage('');
                setIsMenuOpen(false);
                resetFileFolderInput();
            }
        });
    }

    const handleUploadButton = async (event) => {
        let filesForUpload = Array.from(event.target.files || []);
        if (!filesForUpload.length) return;
        uploadFiles(filesForUpload);
    }


    async function handleDroppedFiles(files) {
        uploadFiles(files);
    }

    useEffect(() => {
        if (uploadedFiles.length > 0) {
            useThumbnailGenerator(files, path);
        }
        if (flash.more_info?.replaceAbort) {
            setIsReplaceAbortModalOpen(true)
        }
    }, [uploadedFiles, flash.more_info]);

    return (
        <>
            <FileDropzone onFilesAccepted={handleDroppedFiles}/>
            {isReplaceAbortModalOpen && <ReplaceAbortModal isReplaceAbortModalOpen={isReplaceAbortModalOpen}
                                                           setIsReplaceAbortModalOpen={setIsReplaceAbortModalOpen}/>}

            <div ref={menuRef} className='relative mr-1 p-0'>

                <button className="inline-flex gap-x-1 bg-blue-700 text-white font-bold p-1 md:p-2 rounded hover:bg-blue-600 active:bg-blue-800 items-center text-sm md:text-base
"
                        onClick={() => {
                            setIsMenuOpen(!isMenuOpen)
                        }}
                >
                    <UploadCloudIcon className="w-4 h-4 inline"/>
                    New
                </button>
                {isMenuOpen && (
                    <div
                        className="absolute left-0 mt-2 w-32 text-left rounded-md shadow-lg bg-gray-700 ring-1 ring-black ring-opacity-5 z-10">
                        <div className="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
                            <button
                                onClick={() => {
                                    isFile.current = true;
                                    setIsModalOpen(true);
                                    setIsMenuOpen(false);
                                }}
                                className="text-left block w-full px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 active:bg-gray-800 "
                                role="menuitem"
                            >
                                Create File
                            </button>
                            <button
                                onClick={() => {
                                    isFile.current = false;
                                    setIsModalOpen(true);
                                    setIsMenuOpen(false);
                                }}
                                className="text-left block w-full px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 active:bg-gray-800 "
                                role="menuitem"
                            >
                                Create Folder
                            </button>
                            <button
                                onClick={() => fileInputRef.current.click()}
                                className="text-left block w-full px-4 py-2 text-sm bg-gray-700  hover:bg-gray-600 active:bg-gray-800"
                                role="menuitem"
                            >
                                Upload File
                            </button>
                            <button
                                onClick={() => folderInputRef.current.click()}
                                className="text-left block w-full px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 active:bg-gray-800"
                                role="menuitem"
                            >
                                Upload Folder
                            </button>
                        </div>
                    </div>
                )}
                <CreateItemModal isModalOpen={isModalOpen} setIsModalOpen={setIsModalOpen} path={path} isFile={isFile} />

                <div className="relative inline-block">
                    <input
                        type="file"
                        ref={fileInputRef}
                        className="hidden"
                        onChange={(e) => handleUploadButton(e)}
                        multiple
                    />
                    <input
                        type="file"
                        ref={folderInputRef}
                        className="hidden"
                        onChange={(e) => handleUploadButton(e)}
                        webkitdirectory="true"
                        directory="true"
                    />
                </div>

            </div>
        </>
    )
}

export default UploadMenu;