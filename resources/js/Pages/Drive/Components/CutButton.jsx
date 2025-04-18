import {ScissorsIcon, Trash2Icon} from "lucide-react";
import Button from "./Generic/Button.jsx"

const CutButton = ({classes, onCut}) => {


    return (
        <Button classes={`border border-orange-900 text-orange-200 hover:bg-orange-950 active:bg-orange-900 ${classes}`}
                onClick={onCut}
        >
            <ScissorsIcon className={`text-orange-500  w-4 h-4`}/>
            {!classes && <span className={`mx-1  hidden sm:inline `}>Cut</span>}
        </Button>
    );
};

export default CutButton;