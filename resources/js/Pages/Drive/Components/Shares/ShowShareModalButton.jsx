import { Share2Icon } from "lucide-react";
import Button from "@/Pages/Drive/Components/Generic/Button.jsx";

const ShowShareModalButton = ({
    setIsShareModalOpen,
    classes = "",
    setFilesToShare,
    filesToShare,
    ...props
}) => {
    function handleShareButton(e) {
        e.stopPropagation();
        setIsShareModalOpen(true);
        if (setFilesToShare) {
            setFilesToShare(filesToShare);
        }
    }

    return (
        <Button
            size="selected"
            classes={`border border-blue-700 text-blue-200 hover:bg-blue-950 active:bg-gray-900 ${classes}`}
            {...props}
            aria-label={props["aria-label"] ?? "Share selected files"}
            title={props.title ?? "Share selected files"}
            onClick={(e) => handleShareButton(e)}
        >
            <Share2Icon className={`text-blue-500  h-4 w-4`} />
            {!classes && <span className={`hidden lg:inline`}>Share</span>}
        </Button>
    );
};

export default ShowShareModalButton;
