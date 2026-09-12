"use client";

import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import useClickOutside from "../Hooks/useClickOutside.jsx";
import CreateItemModal from "./CreateFolderModal.jsx";
import useThumbnailGenerator from "@/Pages/Drive/Hooks/useThumbnailGenerator.jsx";
import { UploadCloudIcon } from "lucide-react";
import FileDropzone from "@/Pages/Drive/Components/DropZone.jsx";
import ReplaceAbortModal from "@/Pages/Drive/Components/ReplaceAbortModal.jsx";
import PasswordProtectedUploadModal from "@/Pages/Drive/Components/PasswordProtectedUploadModal.jsx";
import UploadQueueDialog from "@/Pages/Drive/Components/UploadQueueDialog.jsx";
import useUploadQueue from "@/Pages/Drive/Hooks/useUploadQueue.jsx";

const UploadMenu = ({ path, setStatusMessage, files }) => {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isReplaceAbortModalOpen, setIsReplaceAbortModalOpen] =
        useState(false);
    const [uploadedFiles, setUploadedFiles] = useState([]);
    const uploadQueue = useUploadQueue();

    const fileInputRef = useRef(null);
    const folderInputRef = useRef(null);
    const resetFileFolderInput = () => {
        if (fileInputRef.current) {
            fileInputRef.current.value = ""; // Clears the selected files
        }
        if (folderInputRef.current) {
            folderInputRef.current.value = ""; // Clears the selected files
        }
    };

    const menuRef = useRef(null);
    useClickOutside(menuRef, () => setIsMenuOpen(false));
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isPwUploadModalOpen, setIsPwUploadModalOpen] = useState(false);
    const isFile = useRef(false);

    function uploadFiles(selectedFileForUpload, onProgress) {
        setStatusMessage("Uploading...");
        const formData = new FormData();

        selectedFileForUpload.forEach((file) => {
            const fileName =
                file.webkitRelativePath || file.relativePath || file.name;

            formData.append("files[]", file, fileName);
        });

        formData.append("path", path);

        router.post("/upload", formData, {
            only: ["files", "flash"],
            onProgress,
            onSuccess: (page) => {
                setUploadedFiles(selectedFileForUpload);

                if (page.props.flash?.more_info?.replaceAbort) {
                    setIsReplaceAbortModalOpen(true);
                    return;
                }

                uploadQueue.finish();
            },
            onError: (error) => {
                if (error.response?.status === 413) {
                    setStatusMessage(
                        "File too large for server to handle. Please upload a smaller file.",
                    );
                }

                uploadQueue.finish();
            },
            onFinish: () => {
                setStatusMessage("");
                setIsMenuOpen(false);
                resetFileFolderInput();
            },
        });
    }

    const handleUploadButton = async (event) => {
        let filesForUpload = Array.from(event.target.files || []);
        if (!filesForUpload.length) return;
        uploadQueue.add(filesForUpload, uploadFiles);
    };

    async function handleDroppedFiles(files) {
        uploadQueue.add(files, uploadFiles);
    }

    useEffect(() => {
        if (uploadedFiles.length > 0) {
            useThumbnailGenerator(files, path);
        }
    }, [uploadedFiles]);

    return (
        <>
            {!isPwUploadModalOpen && (
                <FileDropzone onFilesAccepted={handleDroppedFiles} />
            )}
            {isReplaceAbortModalOpen && (
                <ReplaceAbortModal
                    isReplaceAbortModalOpen={isReplaceAbortModalOpen}
                    setIsReplaceAbortModalOpen={setIsReplaceAbortModalOpen}
                    onResolved={uploadQueue.finish}
                />
            )}
            <UploadQueueDialog items={uploadQueue.items} />

            <div ref={menuRef} className="relative mr-1 p-0">
                <button
                    type="button"
                    aria-expanded={isMenuOpen}
                    aria-label="New"
                    className="inline-flex justify-center min-h-7 min-w-7 items-center gap-x-1 rounded bg-blue-700 p-1 text-sm font-bold text-white hover:bg-blue-600 active:bg-blue-800"
                    onClick={() => {
                        setIsMenuOpen(!isMenuOpen);
                    }}
                >
                    <UploadCloudIcon className="w-4 h-4 inline" />
                    <span className="hidden sm:inline">New</span>
                </button>
                {isMenuOpen && (
                    <div className="absolute left-0 mt-2 w-32 text-left rounded-md shadow-lg bg-gray-700 ring-1 ring-black ring-opacity-5 z-10">
                        <div
                            className="py-1"
                            role="menu"
                            aria-orientation="vertical"
                            aria-labelledby="options-menu"
                        >
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
                            <button
                                onClick={() => {
                                    setIsPwUploadModalOpen(true);
                                    setIsMenuOpen(false);
                                }}
                                className="text-left block w-full px-4 py-2 text-sm bg-gray-700 hover:bg-gray-600 active:bg-gray-800"
                                role="menuitem"
                            >
                                Upload Encrypted
                            </button>
                        </div>
                    </div>
                )}
                <CreateItemModal
                    isModalOpen={isModalOpen}
                    setIsModalOpen={setIsModalOpen}
                    path={path}
                    isFile={isFile}
                />
                <PasswordProtectedUploadModal
                    isModalOpen={isPwUploadModalOpen}
                    setIsModalOpen={setIsPwUploadModalOpen}
                    path={path}
                    setStatusMessage={setStatusMessage}
                />

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
    );
};

export default UploadMenu;
