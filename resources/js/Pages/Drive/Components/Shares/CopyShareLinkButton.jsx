import { CopyIcon } from "lucide-react";
import { useState } from "react";

const CopyShareLinkButton = ({ sharedLink }) => {
    const [copyToClipboard, setCopyToClipboard] = useState("Copy to clipboard");

    const handleCopy = (sharedLink) => {
        navigator.clipboard?.writeText(sharedLink).then(() => {
            setCopyToClipboard("Copied!");
        });
    };

    return (
        <button
            onClick={(e) => {
                e.preventDefault();
                return handleCopy(sharedLink);
            }}
            className={`p-2 mx-1 rounded-md bg-gray-600 hover:bg-gray-500  relative group active:bg-gray-600`}
        >
            <CopyIcon />
            <span className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-xs text-white bg-black rounded opacity-0 group-hover:opacity-100">
                {copyToClipboard}
            </span>
        </button>
    );
};

export default CopyShareLinkButton;
