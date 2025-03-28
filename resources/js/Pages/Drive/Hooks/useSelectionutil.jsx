import {useCallback, useState} from "react";

function useSelectionUtil() {

    const [selectedFiles, setSelectedFiles] = useState(new Set());
    const [selectAllToggle, setSelectAllToggle] = useState(false);


    function handlerSelectFile(file) {
        setSelectedFiles(prevSelectedFiles => {
            const newSelectedFiles = new Set(prevSelectedFiles);
            newSelectedFiles.has(file.id)
                ? newSelectedFiles.delete(file.id) // Toggle off
                : newSelectedFiles.add(file.id); // Toggle on
            return newSelectedFiles;
        });
    }

    const handlerSelectFileMemo = useCallback(handlerSelectFile, [])

    function handleSelectAllToggle(files) {
        // if false -> select all files | else ->   deselect all files
        if (selectAllToggle) {
            setSelectedFiles(new Set());
            setSelectAllToggle(false);
        } else {
            setSelectedFiles(new Set(files.map(file => file.id)));
            setSelectAllToggle(true);
        }
    }

    return {
        selectAllToggle,
        handleSelectAllToggle,
        selectedFiles,
        setSelectedFiles,
        setSelectAllToggle,
        handlerSelectFileMemo
    }

}

export default useSelectionUtil;