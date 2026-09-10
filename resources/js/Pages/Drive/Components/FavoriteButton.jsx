import { Star } from "lucide-react";
import Button from "./Generic/Button.jsx";

const FavoriteButton = ({
    onClick,
    isFavorite = false,
    label = isFavorite ? "Already a favorite" : "Add to favorites",
    classes = "",
}) => {
    const handleClick = (event) => {
        event.stopPropagation();
        onClick();
    };

    return (
        <Button
            size="selected"
            classes={`border border-blue-700 text-yellow-300 hover:bg-blue-950 active:bg-gray-900 ${isFavorite ? "bg-blue-700" : ""} ${classes}`}
            onClick={handleClick}
            type="button"
            aria-label={label}
            title={label}
        >
            <Star
                className="h-4 w-4"
                fill={isFavorite ? "currentColor" : "none"}
                aria-hidden="true"
            />
            {!classes && <span className="hidden lg:inline">Star</span>}
        </Button>
    );
};

export default FavoriteButton;
