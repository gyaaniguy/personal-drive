import { Star, X } from "lucide-react";
import DownloadButton from "@/Pages/Drive/Components/DownloadButton.jsx";
import ShowShareModalButton from "@/Pages/Drive/Components/Shares/ShowShareModalButton.jsx";
import DropdownMenu from "@/Pages/Drive/Components/Generic/DropdownMenu.jsx";

const FavoritesMenu = ({
    favorites,
    onOpenFavorite,
    onRemoveFavorite,
    setIsShareModalOpen,
    setFilesToShare,
    setStatusMessage,
    setAlertStatus,
}) => {
    return (
        <DropdownMenu
            ariaLabel="Favorites"
            label="Fav"
            panelId="favorites-list"
            width="w-56 sm:w-72"
            icon={
                <Star
                    className="h-4 w-4 fill-current text-yellow-300"
                    aria-hidden="true"
                />
            }
        >
            {({ focusTrigger }) => {
                const handleRemove = async (event, favoriteId) => {
                    event.stopPropagation();
                    await onRemoveFavorite(favoriteId);
                    focusTrigger();
                };

                return (
                    <>
                        <p className="px-1 py-1 text-sm font-medium text-gray-200">
                            Favorites
                        </p>
                        {favorites.length === 0 ? (
                            <p className="px-2 py-2 text-sm text-gray-400">
                                No favorites yet.
                            </p>
                        ) : (
                            <ul className="max-h-96 overflow-y-auto">
                                {favorites.map((favorite) => {
                                    const localFile = favorite.local_file;
                                    if (!localFile) {
                                        return null;
                                    }
                                    const compactPath = localFile.public_path
                                        .split("/")
                                        .filter(Boolean)
                                        .slice(-2)
                                        .join("/");

                                    return (
                                        <li
                                            className="group flex items-center gap-1 rounded px-0 py-0 hover:bg-gray-600 focus-within:bg-gray-600"
                                            key={favorite.id}
                                        >
                                            <button
                                                type="button"
                                                className="min-w-0 flex-1 rounded px-1 py-2 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400"
                                                onClick={() =>
                                                    onOpenFavorite(localFile)
                                                }
                                            >
                                                <span className="flex min-w-0 items-center gap-x-1">
                                                    <span className="truncate-left min-w-0 text-xs text-gray-400">
                                                        /
                                                        {compactPath &&
                                                            `${compactPath}/`}
                                                    </span>
                                                    <span className="shrink-0 text-sm text-gray-100">
                                                        {localFile.filename}
                                                    </span>
                                                </span>
                                            </button>
                                            <div className="flex gap-1 md:opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100">
                                                <DownloadButton
                                                    isAdmin={true}
                                                    classes="h-7 w-7 justify-center"
                                                    selectedFiles={
                                                        new Set([localFile.id])
                                                    }
                                                    setStatusMessage={
                                                        setStatusMessage
                                                    }
                                                    setAlertStatus={
                                                        setAlertStatus
                                                    }
                                                    aria-label={`Download ${localFile.filename}`}
                                                    title={`Download ${localFile.filename}`}
                                                />
                                                <ShowShareModalButton
                                                    classes="h-7 w-7 justify-center"
                                                    setIsShareModalOpen={
                                                        setIsShareModalOpen
                                                    }
                                                    setFilesToShare={
                                                        setFilesToShare
                                                    }
                                                    filesToShare={
                                                        new Set([localFile.id])
                                                    }
                                                    aria-label={`Share ${localFile.filename}`}
                                                    title={`Share ${localFile.filename}`}
                                                />
                                                <button
                                                    type="button"
                                                    className="flex h-7 w-7 items-center justify-center rounded-md text-red-300 hover:bg-red-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                                                    onClick={(event) =>
                                                        handleRemove(
                                                            event,
                                                            favorite.id,
                                                        )
                                                    }
                                                    aria-label={`Remove ${localFile.filename} from favorites`}
                                                    title="Remove favorite"
                                                >
                                                    <X
                                                        className="h-5 w-5"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </>
                );
            }}
        </DropdownMenu>
    );
};

export default FavoritesMenu;
