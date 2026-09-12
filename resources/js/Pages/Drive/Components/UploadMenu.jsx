"use client";

import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import CreateItemModal from "./CreateFolderModal.jsx";
import useThumbnailGenerator from "@/Pages/Drive/Hooks/useThumbnailGenerator.jsx";
import { UploadCloudIcon } from "lucide-react";
import FileDropzone from "@/Pages/Drive/Components/DropZone.jsx";
import ReplaceAbortModal from "@/Pages/Drive/Components/ReplaceAbortModal.jsx";
import PasswordProtectedUploadModal from "@/Pages/Drive/Components/PasswordProtectedUploadModal.jsx";
import UploadQueueDialog from "@/Pages/Drive/Components/UploadQueueDialog.jsx";
import useUploadQueue from "@/Pages/Drive/Hooks/useUploadQueue.jsx";
import DropdownMenu from "@/Pages/Drive/Components/Generic/DropdownMenu.jsx";

const menuItemClass =
    "block w-full rounded px-1 py-2 text-left text-sm hover:bg-gray-600 active:bg-gray-800";

const UploadMenu = ({ path, setStatusMessage, files }) => {
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

            <DropdownMenu
                ariaLabel="New"
                label="New"
                icon={<UploadCloudIcon className="w-4 h-4 inline" />}
                width="w-32"
            >
                {({ close }) => (
                    <div role="menu" aria-orientation="vertical">
                        <button
                            onClick={() => {
                                isFile.current = true;
                                setIsModalOpen(true);
                                close();
                            }}
                            className={menuItemClass}
                            role="menuitem"
                        >
                            Create File
                        </button>
                        <button
                            onClick={() => {
                                isFile.current = false;
                                setIsModalOpen(true);
                                close();
                            }}
                            className={menuItemClass}
                            role="menuitem"
                        >
                            Create Folder
                        </button>
                        <button
                            onClick={() => {
                                fileInputRef.current.click();
                                close();
                            }}
                            className={menuItemClass}
                            role="menuitem"
                        >
                            Upload File
                        </button>
                        <button
                            onClick={() => {
                                folderInputRef.current.click();
                                close();
                            }}
                            className={menuItemClass}
                            role="menuitem"
                        >
                            Upload Folder
                        </button>
                        <button
                            onClick={() => {
                                setIsPwUploadModalOpen(true);
                                close();
                            }}
                            className={menuItemClass}
                            role="menuitem"
                        >
                            Upload Encrypted
                        </button>
                    </div>
                )}
            </DropdownMenu>

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
        </>
    );
};

export default UploadMenu;
