import {
    memo,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from "react";
import { useNavigate } from "react-router-dom";
import { Grid, List, StepBackIcon } from "lucide-react";
import MediaViewer from "./FileList/MediaViewer.jsx";
import TileViewOne from "./FileList/TileViewOne.jsx";
import ListView from "./FileList/ListView.jsx";
import Breadcrumb from "@/Pages/Drive/Components/Breadcrumb.jsx";
import useSelectionUtil from "@/Pages/Drive/Hooks/useSelectionutil.jsx";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";
import ShareModal from "@/Pages/Drive/Components/Shares/ShareModal.jsx";
import DownloadButton from "@/Pages/Drive/Components/DownloadButton.jsx";
import ShowShareModalButton from "@/Pages/Drive/Components/Shares/ShowShareModalButton.jsx";
import DeleteButton from "@/Pages/Drive/Components/DeleteButton.jsx";
import FavoritesMenu from "@/Pages/Drive/Components/FavoritesMenu.jsx";
import FavoriteButton from "@/Pages/Drive/Components/FavoriteButton.jsx";
import UploadMenu from "@/Pages/Drive/Components/UploadMenu.jsx";
import { router, usePage } from "@inertiajs/react";
import RenameModal from "@/Pages/Drive/Components/FileList/RenameModal.jsx";
import CutButton from "./CutButton.jsx";
import PasteButton from "./PasteButton.jsx";
import { CutFilesContext } from "../../../Contexts/CutFilesContext.jsx";
import GoToFolder from "@/Pages/Drive/Components/GoToFolder.jsx";

const FileBrowserSection = memo(
    ({ files, path, token, isAdmin, slug, folderExists, favorites = [] }) => {
        const {
            selectAllToggle,
            handleSelectAllToggle,
            selectedFiles,
            setSelectedFiles,
            setSelectAllToggle,
            handlerSelectFileMemo,
        } = useSelectionUtil();

        const { url } = usePage();

        const [isSearch, setIsSearch] = useState(false);
        const [statusMessage, setStatusMessage] = useState("");
        const [alertStatus, setAlertStatus] = useState(true);
        const alertSeq = useRef(0);
        const [, setAlertRenderSeq] = useState(0);
        const pendingAlertStatus = useRef(null);

        function notify(msg, status = true) {
            if (pendingAlertStatus.current !== null) {
                status = pendingAlertStatus.current;
                pendingAlertStatus.current = null;
            }
            alertSeq.current += 1;
            setAlertRenderSeq(alertSeq.current);
            setStatusMessage(msg);
            setAlertStatus(status);
        }

        function updateAlertStatus(status) {
            pendingAlertStatus.current = status;
            setAlertStatus(status);
        }
        const [filesToShare, setFilesToShare] = useState(new Set());
        const [isShareModalOpen, setIsShareModalOpen] = useState(false);
        const [fileToRename, setFileToRename] = useState(new Set());
        const [isRenameModalOpen, setIsRenameModalOpen] = useState(false);
        const [favoriteItems, setFavoriteItems] = useState(favorites);
        const favoriteFileIds = new Set(
            favoriteItems.map((favorite) => favorite.local_file.id),
        );

        const { cutFiles, setCutFiles, cutPath, setCutPath } =
            useContext(CutFilesContext);

        const handleCut = () => {
            setCutFiles?.(new Set(selectedFiles));
            setSelectedFiles?.(new Set());
            setSelectAllToggle?.(false);
            setCutPath(path);
        };
        const handlePasteFiles = () => {
            if (cutPath === path) {
                alert("Files already in the same path");
                return;
            }
            router.post(
                "/move-files",
                {
                    fileList: Array.from(cutFiles),
                    path: path,
                },
                {
                    preserveState: false,
                    preserveScroll: true,
                    only: ["files", "flash"],
                    onFinish: () => {
                        setCutFiles(new Set());
                    },
                },
            );
        };

        const toggleFavorites = async (
            localFileIds,
            clearSelection = false,
        ) => {
            if (localFileIds.length === 0) {
                return;
            }

            const allFavorited = localFileIds.every((fileId) =>
                favoriteFileIds.has(fileId),
            );

            try {
                if (allFavorited) {
                    const localFileIdSet = new Set(localFileIds);
                    const favoritesToRemove = favoriteItems.filter((favorite) =>
                        localFileIdSet.has(favorite.local_file.id),
                    );

                    await Promise.all(
                        favoritesToRemove.map((favorite) =>
                            axios.delete(`/favorites/${favorite.id}`),
                        ),
                    );
                    setFavoriteItems((items) =>
                        items.filter(
                            (favorite) =>
                                !localFileIdSet.has(favorite.local_file.id),
                        ),
                    );
                } else {
                    const response = await axios.post("/favorites", {
                        local_file_ids: localFileIds.filter(
                            (fileId) => !favoriteFileIds.has(fileId),
                        ),
                    });
                    setFavoriteItems(response.data.favorites);
                }

                if (clearSelection) {
                    setSelectedFiles(new Set());
                    setSelectAllToggle(false);
                }

                notify(
                    allFavorited
                        ? "Removed from favorites"
                        : "Added to favorites",
                );
            } catch {
                notify("Could not update favorites", false);
            }
        };

        const handleAddFavorites = () =>
            toggleFavorites(Array.from(selectedFiles), true);

        const handleAddFavorite = (localFileId) =>
            toggleFavorites([localFileId]);

        const handleRemoveFavorite = (favoriteId) => {
            const favorite = favoriteItems.find(
                (item) => item.id === favoriteId,
            );

            if (favorite) {
                return toggleFavorites([favorite.local_file.id]);
            }
        };

        const handleOpenFavorite = (localFile) => {
            const favoritePath = localFile.is_dir
                ? [localFile.public_path, localFile.filename]
                      .filter(Boolean)
                      .join("/")
                : localFile.public_path;

            if (!localFile.is_dir) {
                sessionStorage.setItem("favorite-file-id", localFile.id);
            }

            router.visit(favoritePath ? `/drive/${favoritePath}` : "/drive");
        };

        const navigate = useNavigate();

        // Preview
        let textFileTypes = ["text", "txt", "csv", "ini"];
        let previewAbleTypes = useRef([
            "empty",
            "html",
            "image",
            "video",
            "audio",
            "pdf",
            ...textFileTypes,
        ]);
        let previewAbleFiles = useRef([]);
        const [previewFile, setPreviewFile] = useState(null);
        const [isPreviewModalOpen, setPreviewIsModalOpen] = useState(false);

        function selectFileForPreview(file) {
            setPreviewFile(file);
        }

        function handleFileClick(file) {
            if (previewAbleTypes.current.includes(file.file_type)) {
                setPreviewIsModalOpen(true);
                selectFileForPreview(file);
            }
        }

        let handleFileClickM = useCallback(handleFileClick, [previewAbleFiles]);

        // view mode
        let viewModes = ["ListView", "TileViewOne"];
        const [currentViewMode, setCurrentViewMode] = useState(
            localStorage.getItem("viewMode") || viewModes[0],
        );

        function handleViewModeClick(mode) {
            setCurrentViewMode(mode);
            localStorage.setItem("viewMode", mode);
        }

        // Sorting
        const [filesCopy, setFilesCopy] = useState([...files]);
        const sortDetails = JSON.parse(localStorage.getItem("sortDetails")) || {
            key: "filename",
            order: "desc",
        };

        function sortArrayByKey(arr, key, direction) {
            return [...arr].sort((a, b) => {
                let valA =
                    a[key]?.toLowerCase?.() || (a[key] != null ? a[key] : "");
                let valB =
                    b[key]?.toLowerCase?.() || (b[key] != null ? b[key] : "");
                // empty string are for folders sizes
                valA = valA === "" ? -1 : valA;
                valB = valB === "" ? -1 : valB;

                if (direction === "desc") {
                    return valA > valB ? -1 : valA < valB ? 1 : 0;
                } else {
                    return valA < valB ? -1 : valA > valB ? 1 : 0;
                }
            });
        }

        function sortCol(files, key, changeDirection = true) {
            let sortDirectionToSet = "desc";
            if (key === sortDetails.key) {
                sortDirectionToSet = sortDetails.order;
            }
            if (!changeDirection) {
                sortDirectionToSet =
                    sortDirectionToSet === "desc" ? "asc" : "desc";
            }
            let sortedFiles = sortArrayByKey(files, key, sortDirectionToSet);
            sortDetails.key = key;
            sortDetails.order = sortDirectionToSet === "desc" ? "asc" : "desc";
            localStorage.setItem("sortDetails", JSON.stringify(sortDetails));
            return sortedFiles;
        }

        function getPrevieAbleFiles(files) {
            let previewAbleFilesPotential = files.filter((file) =>
                previewAbleTypes.current.includes(file.file_type),
            );
            for (let i = 0; i < previewAbleFilesPotential.length; i++) {
                previewAbleFilesPotential[i]["next"] =
                    previewAbleFilesPotential[i + 1]?.id || null;
                previewAbleFilesPotential[i]["prev"] =
                    previewAbleFilesPotential[i - 1]?.id || null;
            }
            return previewAbleFilesPotential;
        }

        useEffect(() => {
            // initial sort
            let previewAbleFilesPotential;
            if (sortDetails.key) {
                let sortedFiles = sortCol(files, sortDetails.key, false);
                setFilesCopy([...sortedFiles]);
                previewAbleFilesPotential = getPrevieAbleFiles(sortedFiles);
            } else {
                setFilesCopy([...files]);
                previewAbleFilesPotential = getPrevieAbleFiles(files);
            }
            // Generate previewable files
            previewAbleFiles.current = previewAbleFilesPotential;

            if (url.includes("search-files")) {
                setIsSearch(true);
                // A selection made before searching must not stay active on the
                // results page, or a bulk action would hit off-screen pre-search
                // files (wrong-target delete). Clear it when entering search.
                setSelectedFiles(new Set());
                setSelectAllToggle(false);
            }
        }, [files]);

        useEffect(() => {
            setFilesToShare(selectedFiles);
        }, [selectedFiles]);

        useEffect(() => {
            setFavoriteItems(favorites);
        }, [favorites]);

        useEffect(() => {
            const favoriteFileId = sessionStorage.getItem("favorite-file-id");

            if (!favoriteFileId) {
                return;
            }

            let removeHighlightTimer;
            const findFileTimer = window.setTimeout(() => {
                const favoriteFile = document.querySelector(
                    `[data-file-id="${favoriteFileId}"]`,
                );

                sessionStorage.removeItem("favorite-file-id");

                if (!favoriteFile) {
                    return;
                }

                favoriteFile.scrollIntoView({
                    behavior: "smooth",
                    block: "center",
                });
                favoriteFile.classList.add("ring-2", "ring-yellow-400");
                removeHighlightTimer = window.setTimeout(() => {
                    favoriteFile.classList.remove("ring-2", "ring-yellow-400");
                }, 1500);
            }, 0);

            return () => {
                window.clearTimeout(findFileTimer);
                window.clearTimeout(removeHighlightTimer);
            };
        }, [filesCopy]);

        return (
            <div className="min-h-screen rounded-md">
                {isAdmin && <GoToFolder />}
                <ShareModal
                    isShareModalOpen={isShareModalOpen}
                    setIsShareModalOpen={setIsShareModalOpen}
                    setSelectedFiles={setSelectedFiles}
                    selectedFiles={filesToShare}
                    setSelectAllToggle={setSelectAllToggle}
                    path={path}
                />

                <RenameModal
                    isRenameModalOpen={isRenameModalOpen}
                    setIsRenameModalOpen={setIsRenameModalOpen}
                    setFileToRename={setFileToRename}
                    fileToRename={fileToRename}
                    path={path}
                />

                <AlertBox
                    key={alertSeq.current}
                    message={statusMessage}
                    alertStatus={alertStatus}
                />

                <div className="contents lg:flex lg:sticky lg:top-0 lg:z-20 lg:flex-row lg:justify-end lg:gap-x-2 lg:rounded-md lg:bg-gray-800 lg:mt-5">
                    <Breadcrumb path={path} isAdmin={isAdmin} />
                    <div className="sticky top-0 z-20 flex w-full min-w-0 items-center justify-end gap-x-1 bg-gray-800 py-1 lg:static lg:w-auto lg:shrink-0 justify-self-end">
                        {selectedFiles.size > 0 && (
                            <div className="flex min-h-5 shrink-0 gap-x-1">
                                <DownloadButton
                                    isAdmin={isAdmin}
                                    setSelectedFiles={setSelectedFiles}
                                    selectedFiles={selectedFiles}
                                    setStatusMessage={notify}
                                    setSelectAllToggle={setSelectAllToggle}
                                    slug={slug}
                                    setAlertStatus={updateAlertStatus}
                                />
                                {isAdmin && (
                                    <FavoriteButton
                                        onClick={handleAddFavorites}
                                        label="Add selected items to favorites"
                                    />
                                )}
                                {isAdmin && (
                                    <>
                                        <ShowShareModalButton
                                            setIsShareModalOpen={
                                                setIsShareModalOpen
                                            }
                                        />
                                        {cutFiles.size === 0 && (
                                            <CutButton onCut={handleCut} />
                                        )}
                                        <DeleteButton
                                            setSelectedFiles={setSelectedFiles}
                                            selectedFiles={selectedFiles}
                                            setSelectAllToggle={
                                                setSelectAllToggle
                                            }
                                        />
                                    </>
                                )}
                            </div>
                        )}
                        {cutFiles.size > 0 && path && (
                            <PasteButton
                                onPaste={handlePasteFiles} // Example paste handler
                            />
                        )}
                        <div className="flex min-h-5 shrink-0 items-center justify-end">
                            {isAdmin && (
                                <FavoritesMenu
                                    favorites={favoriteItems}
                                    onOpenFavorite={handleOpenFavorite}
                                    onRemoveFavorite={handleRemoveFavorite}
                                    setIsShareModalOpen={setIsShareModalOpen}
                                    setFilesToShare={setFilesToShare}
                                    setStatusMessage={notify}
                                    statusMessage={statusMessage}
                                    setAlertStatus={updateAlertStatus}
                                />
                            )}
                            {!isSearch && isAdmin && (
                                <UploadMenu
                                    path={path}
                                    setStatusMessage={notify}
                                    files={files}
                                />
                            )}
                            <div className="flex">
                                <button
                                    aria-label="Tile view"
                                    className={`inline-flex min-h-7 min-w-7 items-center justify-center p-1 mx-1 rounded-md ${currentViewMode === "TileViewOne" ? "bg-gray-900 border border-blue-300" : "bg-gray-600"} hover:bg-gray-500 active:bg-gray-800`}
                                    onClick={() =>
                                        handleViewModeClick("TileViewOne")
                                    }
                                >
                                    <Grid className="w-4 h-4" />
                                </button>
                                <button
                                    aria-label="List view"
                                    className={`inline-flex min-h-7 min-w-7 items-center justify-center p-1 ml-1 text-white rounded-md ${currentViewMode === "ListView" ? "bg-gray-900 border border-blue-300" : "bg-gray-600"} hover:bg-gray-500 active:bg-gray-800`}
                                    onClick={() =>
                                        handleViewModeClick("ListView")
                                    }
                                >
                                    <List className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {previewFile && isPreviewModalOpen && (
                    <MediaViewer
                        previewFile={previewFile}
                        isModalOpen={isPreviewModalOpen}
                        setIsModalOpen={setPreviewIsModalOpen}
                        selectFileForPreview={selectFileForPreview}
                        previewAbleFiles={previewAbleFiles}
                        slug={slug}
                        isAdmin={isAdmin}
                    />
                )}

                <div className="my-3 md:my-8">
                    {filesCopy.length > 0 && (
                        <>
                            {currentViewMode === "TileViewOne" && (
                                <TileViewOne
                                    filesCopy={filesCopy}
                                    token={token}
                                    setStatusMessage={notify}
                                    setAlertStatus={updateAlertStatus}
                                    handleFileClick={handleFileClickM}
                                    isSearch={isSearch}
                                    sortCol={sortCol}
                                    sortDetails={sortDetails}
                                    setFilesCopy={setFilesCopy}
                                    path={path}
                                    selectedFiles={selectedFiles}
                                    handlerSelectFile={handlerSelectFileMemo}
                                    selectAllToggle={selectAllToggle}
                                    handleSelectAllToggle={
                                        handleSelectAllToggle
                                    }
                                    setIsShareModalOpen={setIsShareModalOpen}
                                    setFilesToShare={setFilesToShare}
                                    isAdmin={isAdmin}
                                    slug={slug}
                                    setSelectedFiles={setSelectedFiles}
                                    setIsRenameModalOpen={setIsRenameModalOpen}
                                    setFileToRename={setFileToRename}
                                    favoriteFileIds={favoriteFileIds}
                                    onAddFavorite={handleAddFavorite}
                                />
                            )}
                            {currentViewMode === "ListView" && (
                                <ListView
                                    filesCopy={filesCopy}
                                    token={token}
                                    setStatusMessage={notify}
                                    setAlertStatus={updateAlertStatus}
                                    handleFileClick={handleFileClickM}
                                    isSearch={isSearch}
                                    sortCol={sortCol}
                                    sortDetails={sortDetails}
                                    setFilesCopy={setFilesCopy}
                                    path={path}
                                    selectedFiles={selectedFiles}
                                    handlerSelectFile={handlerSelectFileMemo}
                                    selectAllToggle={selectAllToggle}
                                    handleSelectAllToggle={
                                        handleSelectAllToggle
                                    }
                                    setIsShareModalOpen={setIsShareModalOpen}
                                    setFilesToShare={setFilesToShare}
                                    isAdmin={isAdmin}
                                    slug={slug}
                                    setSelectedFiles={setSelectedFiles}
                                    setIsRenameModalOpen={setIsRenameModalOpen}
                                    setFileToRename={setFileToRename}
                                    favoriteFileIds={favoriteFileIds}
                                    onAddFavorite={handleAddFavorite}
                                />
                            )}
                        </>
                    )}

                    {filesCopy.length === 0 && (
                        <div className="py-20 w-full">
                            <div className="flex items-center justify-center gap-x-4 ">
                                <span className="text-xl">
                                    {folderExists
                                        ? "Empty Results"
                                        : "This folder does not exist"}
                                </span>
                                <button
                                    className="p-2 rounded-md bg-gray-700 hover:bg-gray-600"
                                    onClick={() => navigate(-1)}
                                >
                                    <StepBackIcon
                                        className={`text-gray-500 inline`}
                                        size={22}
                                    />
                                    <span className={`mx-1`}>Go Back</span>
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        );
    },
);

export default FileBrowserSection;
