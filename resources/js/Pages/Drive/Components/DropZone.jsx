import { useCallback, useEffect, useState } from "react";
import { useDropzone } from "react-dropzone";

function FileDropzone({ onFilesAccepted }) {
    const [isDragActive, setIsDragActive] = useState(false);

    useEffect(() => {
        const handleDragEnter = (e) => {
            if (e.dataTransfer?.types?.includes("Files")) {
                setIsDragActive(true);
            }
        };

        const handleDragLeave = (e) => {
            if (e.relatedTarget === null) {
                setIsDragActive(false);
            }
        };

        window.addEventListener("dragenter", handleDragEnter);
        window.addEventListener("dragleave", handleDragLeave);
        window.addEventListener("drop", () => setIsDragActive(false));

        return () => {
            window.removeEventListener("dragenter", handleDragEnter);
            window.removeEventListener("dragleave", handleDragLeave);
            window.removeEventListener("drop", () => setIsDragActive(false));
        };
    }, []);

    const onDrop = useCallback(
        (acceptedFiles) => {
            setIsDragActive(false);
            onFilesAccepted(acceptedFiles);
        },
        [onFilesAccepted],
    );

    const { getRootProps, getInputProps, isDragAccept } = useDropzone({
        onDrop,
        noClick: true,
        noKeyboard: true,
    });

    if (!isDragActive) return null;

    return (
        <div
            {...getRootProps()}
            onClick={() => setIsDragActive(false)} // Click anywhere to dismiss
            style={{
                position: "fixed",
                top: 0,
                left: 0,
                width: "100vw",
                height: "100vh",
                backgroundColor: "rgba(0, 0, 0, 0.5)",
                zIndex: 9999,
                display: "flex",
                justifyContent: "center",
                alignItems: "center",
                border: `4px dashed ${isDragAccept ? "#00e676" : "#ffffff"}`,
                borderRadius: "8px",
                color: "white",
                fontSize: "24px",
                cursor: "pointer",
            }}
        >
            <input {...getInputProps()} />
            {isDragAccept
                ? "Drop files here to upload"
                : "Drag files here to upload"}
        </div>
    );
}

export default FileDropzone;
