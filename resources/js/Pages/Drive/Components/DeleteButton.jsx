import { Trash2Icon } from "lucide-react";
import { router } from "@inertiajs/react";
import Button from "./Generic/Button.jsx";

const DeleteButton = ({
    setSelectedFiles,
    selectedFiles,
    classes,
    setSelectAllToggle,
}) => {
    const confirmAndDelete = (e) => {
        e.stopPropagation();
        if (window.confirm("Confirm Deletion?")) {
            deleteFilesComponentHandler();
        }
    };

    async function deleteFilesComponentHandler() {
        router.post(
            "/delete-files",
            {
                fileList: Array.from(selectedFiles),
            },
            {
                preserveState: true,
                preserveScroll: true,
                only: ["files", "flash"],
                onFinish: () => {
                    setSelectedFiles?.(new Set());
                    setSelectAllToggle?.(false);
                },
            },
        );
    }

    return (
        <Button
            size="selected"
            classes={`border border-red-900 text-red-200 hover:bg-red-950 active:bg-gray-900 ${classes}`}
            onClick={confirmAndDelete}
            aria-label="Delete selected files"
            title="Delete selected files"
        >
            <Trash2Icon className={`text-red-500 w-4 h-4`} />
            {!classes && <span className={`hidden lg:inline`}>Delete</span>}
        </Button>
    );
};

export default DeleteButton;
