import {TextCursorIcon} from "lucide-react";
import Button from "@/Pages/Drive/Components/Generic/Button.jsx";


const RenameModalButton = ({classes = '', setIsRenameModalOpen, setFileToRename, fileToRename}) => {
    function handleShareButton(e) {
        e.stopPropagation();
        setIsRenameModalOpen(true);
        setFileToRename(fileToRename);
    }

    return (
        <Button classes={`border border-blue-700 text-blue-200 hover:bg-cyan-950 active:bg-gray-900 ${classes}`}
                onClick={(e) => handleShareButton(e)}>
            <TextCursorIcon className={`text-lime-500  hidden sm:inline  h-4 w-4`}/>
            {!classes && <span className={`mx-1`}>Share</span>}
        </Button>
    );
};

export default RenameModalButton;