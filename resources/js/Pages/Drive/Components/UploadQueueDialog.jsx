const getUploadStatus = (item) => {
    if (item.status === "queued") return "Queued";
    if (item.status === "processing") return "Processing...";
    if (item.progress == null) return "Uploading...";

    return `${item.progress}%`;
};

const UploadQueueDialog = ({ items, canCancel, onCancel }) => {
    if (!items.length) return null;

    return (
        <aside
            aria-live="polite"
            className="fixed bottom-4 right-4 z-50 w-80 rounded bg-gray-800 p-2 text-sm text-gray-100 shadow-lg text-gray-300"
        >
            <h2 className="mb-2 font-semibold text-gray-400">Uploads</h2>

            <ul className="flex flex-col gap-2">
                {items.map((item) => (
                    <li key={item.id} className="flex flex-col gap-1">
                        <div className="flex justify-between gap-3 ">
                            <span className="truncate">{item.name}</span>
                            <span className="flex shrink-0 items-center gap-2">
                                <span className="text-green-400 text-xs">
                                    {getUploadStatus(item)}
                                </span>
                                {canCancel(item) && (
                                    <button
                                        type="button"
                                        aria-label={`Cancel ${item.name} upload`}
                                        title="Cancel upload"
                                        className="text-gray-400 hover:text-red-400"
                                        onClick={() => onCancel(item.id)}
                                    >
                                        ✕
                                    </button>
                                )}
                            </span>
                        </div>
                        {item.status !== "queued" && (
                            <progress
                                aria-label={`${item.name} upload progress`}
                                className="h-0.5 w-full upload-progress"
                                max="100"
                                value={item.progress ?? 0}
                            />
                        )}
                    </li>
                ))}
            </ul>
        </aside>
    );
};

export default UploadQueueDialog;
