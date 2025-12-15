import { DownloadIcon } from "lucide-react";
import Button from "./Generic/Button.jsx";
import NProgress from "nprogress";

const DownloadButton = ({
    setSelectedFiles,
    selectedFiles,
    classes,
    setStatusMessage,
    statusMessage,
    setSelectAllToggle,
    slug,
    setAlertStatus,
}) => {
    function generateAndClickDownloadLink(response) {
        // Create blob link to download
        const blob = new Blob([response.data], {
            type: response.headers["content-type"],
        });
        const url = window.URL.createObjectURL(blob);
        // Create temporary link and trigger download
        const link = document.createElement("a");
        link.href = url;
        // Try to get filename from response headers
        const contentDisposition = response.headers["content-disposition"];
        const fileName = contentDisposition
            ? contentDisposition.split("filename=")[1].replace(/['"]/g, "")
            : "download";
        link.setAttribute("download", fileName);
        document.body.appendChild(link);
        link.click();
        // Cleanup
        link.parentNode.removeChild(link);
        window.URL.revokeObjectURL(url);
    }

    const handleDownload = async (e) => {
        e.stopPropagation();
        let response = {};
        NProgress.start();
        try {
            setStatusMessage("Downloading...");
            setAlertStatus(true);
            // setIsLoading(true);
            let formData = {
                fileList: Array.from(selectedFiles),
            };
            if (slug) {
                formData["slug"] = slug;
            }
            NProgress.start();

            response = await axios({
                url: "/download-files",
                method: "POST",
                responseType: "blob",
                data: formData,
                onDownloadProgress: (e) => {
                    if (!e.total) return;
                    NProgress.set(e.loaded / e.total);
                },
            });
            NProgress.done();
        } catch {
            NProgress.done(true);
        } finally {
            setStatusMessage("");
            setSelectedFiles?.(new Set());
            setSelectAllToggle?.(false);
        }
        // Check if the response is JSON
        const contentType = response.headers["content-type"];
        if (contentType && contentType == "application/json") {
            // Convert blob to JSON
            const text = await response.data.text();
            const jsonResponse = JSON.parse(text);
            if (!jsonResponse.status && !jsonResponse.message) {
                generateAndClickDownloadLink(response);
            }
            if (!jsonResponse.status && jsonResponse.message) {
                setAlertStatus(jsonResponse.status);
                setStatusMessage("Download failed " + jsonResponse.message);
            }
        } else {
            generateAndClickDownloadLink(response);
        }
    };
    return (
        <Button
            classes={`border border-green-800 text-green-200 hover:bg-green-950 active:bg-gray-900 ${classes} ${statusMessage ? "cursor-not-allowed" : ""}`}
            disabled={statusMessage}
            onClick={handleDownload}
        >
            {statusMessage ? (
                <div className="w-5 h-5 border-t-2 border-blue-300 border-solid rounded-full animate-spin"></div>
            ) : (
                <>
                    <DownloadIcon className="text-center text-green-500  w-4 h-4" />{" "}
                    {!classes && (
                        <span className="mx-1  hidden sm:inline ">
                            Download
                        </span>
                    )}
                </>
            )}
        </Button>
    );
};

export default DownloadButton;
