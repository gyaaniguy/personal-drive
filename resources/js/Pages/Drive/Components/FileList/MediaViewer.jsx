import Modal from "@/Pages/Drive/Components/Modal.jsx";
import VideoPlayer from "./VideoPlayer.jsx";
import ImageViewer from "./ImageViewer.jsx";
import { ChevronLeft, ChevronRight } from "lucide-react";
import {
    lazy,
    Suspense,
    useCallback,
    useEffect,
    useRef,
    useState,
} from "react";
import HtmlViewer from "@/Pages/Drive/Components/FileList/HtmlViewer.jsx";
import AudioPlayer from "@/Pages/Drive/Components/FileList/AudioPlayer.jsx";

const PdfViewer = lazy(() => import("./PdfViewer.jsx"));
const TxtViewer = lazy(() => import("./TxtViewer.jsx"));

const MediaViewer = ({
    previewFile,
    isModalOpen,
    setIsModalOpen,
    selectFileForPreview,
    previewAbleFiles,
    slug,
    isAdmin,
}) => {
    const [isActive, setIsActive] = useState(false);
    const isEditingRef = useRef(false);
    const [isInEditMode, setIsInEditMode] = useState(false);
    const isFocusedRef = useRef(false);
    const timeoutRef = useRef(null);
    let selectedFileType = previewFile.file_type;
    let selectedid = previewFile.id;
    let currentFileIndex = previewAbleFiles.current.findIndex(
        (file) => file.id === selectedid,
    );

    function keepEditing() {
        if (isFocusedRef.current) {
            return true;
        }
        if (
            isEditingRef.current &&
            !confirm("Discard changes? File has been edited.")
        ) {
            return true;
        }
        isEditingRef.current = false;
        return false;
    }

    function prevClick() {
        if (keepEditing()) {
            return;
        }
        if (previewAbleFiles.current[currentFileIndex]["prev"]) {
            let file = previewAbleFiles.current[--currentFileIndex];
            selectFileForPreview(file);
        }
    }

    function nextClick() {
        if (keepEditing()) {
            return;
        }
        if (previewAbleFiles.current[currentFileIndex]["next"]) {
            let file = previewAbleFiles.current[++currentFileIndex];
            selectFileForPreview(file);
        }
    }

    const handleKeyDown = useCallback(
        (event) => {
            if (event.key === "ArrowLeft") {
                prevClick();
            }
            if (event.key === "ArrowRight") {
                nextClick();
            }
            if (event.key === "Escape") {
                onCloseModal();
            }
        },
        [prevClick, nextClick, isEditingRef, isFocusedRef],
    );

    useEffect(() => {
        if ("ontouchstart" in window || navigator.maxTouchPoints > 0) {
            setIsActive(true);
            return;
        }
        window.addEventListener("keydown", handleKeyDown);
        window.addEventListener("mousemove", handleMouseMove);
        return () => {
            window.removeEventListener("keydown", handleKeyDown);
            window.removeEventListener("mousemove", handleMouseMove);
        };
    }, [isModalOpen]);

    function handleMouseMove() {
        if (!isModalOpen) {
            return;
        }
        setIsActive(true);
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
        timeoutRef.current = setTimeout(() => {
            setIsActive(false);
        }, 10000);
    }

    function onCloseModal() {
        if (keepEditing()) {
            return;
        }
        setIsInEditMode(false);
        setIsModalOpen(false);
    }

    return (
        <Modal isOpen={isModalOpen} onClose={onCloseModal} classes={` mx-auto`}>
            <div className=" mx-auto ">
                {previewAbleFiles &&
                    previewAbleFiles.current[currentFileIndex] &&
                    previewAbleFiles.current[currentFileIndex].prev && (
                        <button
                            onClick={prevClick}
                            className={`absolute ${isActive ? "block" : "hidden"} left-2 md:left-8 sm:left-16  top-3/4 p-1 md:p-2 rounded-full hover:bg-gray-500 bg-gray-500  opacity-60  focus:outline-none z-50`}
                        >
                            <ChevronLeft className="text-white h-4 w-4 md:h-8 md:w-8 rounded-full" />
                        </button>
                    )}

                {previewAbleFiles &&
                    previewAbleFiles.current[currentFileIndex] &&
                    previewAbleFiles.current[currentFileIndex].next && (
                        <button
                            onClick={nextClick}
                            className={`absolute ${isActive ? "block" : "hidden"}  right-2 md:right-8 sm:right-16  top-3/4 p-1 md:p-2 rounded-full hover:bg-gray-500 bg-gray-500  opacity-60  focus:outline-none z-50`}
                        >
                            <ChevronRight className="text-white h-4 w-4 md:h-8 md:w-8 rounded-full" />
                        </button>
                    )}
                <Suspense
                    fallback={
                        <div className="p-4 text-center text-gray-300">
                            Loading viewer...
                        </div>
                    }
                >
                    {selectedid &&
                        ((selectedFileType === "video" && (
                            <VideoPlayer id={selectedid} slug={slug} />
                        )) ||
                            (selectedFileType === "image" && (
                                <ImageViewer id={selectedid} slug={slug} />
                            )) ||
                            (selectedFileType === "audio" && (
                                <AudioPlayer id={selectedid} slug={slug} />
                            )) ||
                            (selectedFileType === "html" && (
                                <HtmlViewer id={selectedid} slug={slug} />
                            )) ||
                            (selectedFileType === "pdf" && (
                                <PdfViewer id={selectedid} slug={slug} />
                            )) ||
                            (["text", "empty", "txt", "csv", "ini"].includes(
                                selectedFileType,
                            ) && (
                                <TxtViewer
                                    key={previewFile.id}
                                    previewFile={previewFile}
                                    slug={slug}
                                    isEditingRef={isEditingRef}
                                    isFocusedRef={isFocusedRef}
                                    isInEditMode={isInEditMode}
                                    setIsInEditMode={setIsInEditMode}
                                    isAdmin={isAdmin}
                                />
                            )))}
                </Suspense>
            </div>
        </Modal>
    );
};

export default MediaViewer;
