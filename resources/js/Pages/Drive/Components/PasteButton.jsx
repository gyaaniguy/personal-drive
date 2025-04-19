import {ClipboardCopyIcon} from "lucide-react";
import Button from "./Generic/Button.jsx"

const PasteButton = ({classes, onPaste}) => {
    return (
        <Button classes={`border border-orange-900 text-orange-200 hover:bg-orange-950 active:bg-orange-900 ${classes}`}
                onClick={onPaste}
        >
            <ClipboardCopyIcon className={`text-orange-500  w-4 h-4`}/>
            {!classes && <span className={`mx-1  hidden sm:inline `}>Paste</span>}
        </Button>
    );
};

export default PasteButton;