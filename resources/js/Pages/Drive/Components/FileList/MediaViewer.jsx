import Modal from "@/Pages/Drive/Components/Modal.jsx";
import VideoPlayer from "./VideoPlayer.jsx";
import ImageViewer from "./ImageViewer.jsx";
import {ChevronLeft, ChevronRight} from 'lucide-react';
import {useCallback, useEffect, useRef, useState} from "react";
import PdfViewer from "@/Pages/Drive/Components/FileList/PdfViewer.jsx";
import TxtViewer from "@/Pages/Drive/Components/FileList/TxtViewer.jsx";

const MediaViewer = ({
                         selectedid,
                         selectedFileType,
                         isModalOpen,
                         setIsModalOpen,
                         selectFileForPreview,
                         previewAbleFiles,
                         slug
                     }) => {
    const [isActive, setIsActive] = useState(false);
    const isEditingRef = useRef(false);
    const [isInEditMode, setIsInEditMode] = useState(false);

    
    const timeoutRef = useRef(null);
    let currentFileIndex = previewAbleFiles.current.findIndex(file => file.id === selectedid);

    function keepEditing(){
        console.log('isEditing ', isEditingRef.val);
        if (isEditingRef.current && !confirm('Discard changes? File has been edited.')) {
            return true;
        }
        isEditingRef.current = false;
        return false;
    }
    function prevClick() {
        if (keepEditing()) {
            return ;
        }
        if (previewAbleFiles.current[currentFileIndex]['prev']) {
            let file = previewAbleFiles.current[--currentFileIndex];
            selectFileForPreview(file);
        }
    }

    function nextClick() {
        if (keepEditing()) {
            return ;
        }
        if (previewAbleFiles.current[currentFileIndex]['next']) {
            let file = previewAbleFiles.current[++currentFileIndex];
            selectFileForPreview(file);
        }
    }

    const handleKeyDown = useCallback((event) => {
        if (event.key === 'ArrowLeft') {
            prevClick();
        }
        if (event.key === 'ArrowRight') {
            nextClick();
        }
        if (event.key === 'Escape') {
            setIsModalOpen(false);
        }
    }, [prevClick, nextClick, isEditingRef]);

    useEffect(() => {
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            setIsActive(true);
            return;
        } 
        window.addEventListener('keydown', handleKeyDown);
        window.addEventListener('mousemove', handleMouseMove);
        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener('mousemove', handleMouseMove);
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
            return ;
        }
        setIsModalOpen(false);
    }


    return (
        <Modal isOpen={isModalOpen} onClose={onCloseModal} classes={` mx-auto`}>
            <div className=" mx-auto ">
                {previewAbleFiles && previewAbleFiles.current[currentFileIndex] && previewAbleFiles.current[currentFileIndex].prev &&
                    <button onClick={prevClick}
                            className={`absolute ${isActive ? 'block' : 'hidden'} left-8 sm:left-16  top-1/2   p-2 rounded-full hover:bg-gray-500 bg-gray-500  opacity-60  focus:outline-none z-90`}
                    >
                        <ChevronLeft className="text-white h-8 w-8 rounded-full"/>
                    </button>}

                {previewAbleFiles && previewAbleFiles.current[currentFileIndex] && previewAbleFiles.current[currentFileIndex].next &&
                    <button onClick={nextClick}
                            className={`absolute ${isActive ? 'block' : 'hidden'}  right-8 sm:right-16  top-1/2   p-2 rounded-full hover:bg-gray-500 bg-gray-500  opacity-60  focus:outline-none z-50`}
                    >
                        <ChevronRight className="text-white h-8 w-8 rounded-full"/>
                    </button>}
                {selectedid && (
                    (selectedFileType === 'video' && <VideoPlayer id={selectedid} slug={slug}/>) ||
                    (selectedFileType === 'image' && <ImageViewer id={selectedid} slug={slug}/>) ||
                    (selectedFileType === 'pdf' && <PdfViewer id={selectedid} slug={slug}/>) ||
                    (selectedFileType === 'text' && <TxtViewer id={selectedid} slug={slug} isEditingRef={isEditingRef} isInEditMode={isInEditMode} setIsInEditMode={setIsInEditMode} />))
                }
            </div>
        </Modal>);
};

export default MediaViewer;