import {Trash2Icon} from "lucide-react";
import {router} from "@inertiajs/react";
import Button from "./Generic/Button.jsx"

const DeleteButton = ({setSelectedFiles, selectedFiles, classes, setSelectAllToggle}) => {
    const confirmAndDelete = (e) => {
        if (window.confirm('Confirm Deletion?')) {
            deleteFilesComponentHandler(e);
        }
    };

    async function deleteFilesComponentHandler(e) {
        e.stopPropagation();
        router.post('/delete-files', {
            fileList: Array.from(selectedFiles)
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['files', 'flash'],
            onFinish: () => {
                setSelectedFiles?.(new Set());
                setSelectAllToggle?.(false);
            }
        });
    }

    return (
        <Button classes={`border border-red-900 text-red-200 hover:bg-red-950 active:bg-gray-900 ${classes}`}
                onClick={confirmAndDelete}
        >
            <Trash2Icon className={`text-red-500 w-4 h-4`}/>
            {!classes && <span className={` hidden sm:inline  mx-1`}>Delete</span>}
        </Button>
    );
};

export default DeleteButton;