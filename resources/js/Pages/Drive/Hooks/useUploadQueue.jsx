import { useEffect, useRef, useState } from "react";

const STORAGE_KEY = "personal-drive-uploads";

// Identifies this tab; only the tab that started an upload can cancel it.
const TAB_ID = crypto.randomUUID();

const readQueue = () => JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");

const getName = (files) => {
    if (files.length !== 1) return `${files.length} files`;

    return files[0].webkitRelativePath?.split("/")[0] || files[0].name;
};

const useUploadQueue = () => {
    const queueRef = useRef(readQueue());
    const doneRef = useRef(null);
    const cancelTokensRef = useRef(new Map());
    const cancelledIdsRef = useRef(new Set());
    const [items, setItems] = useState(queueRef.current);

    const save = (nextItems) => {
        queueRef.current = nextItems;
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(nextItems, (key, value) =>
                key === "progress" ? undefined : value,
            ),
        );
        setItems(nextItems);
    };

    const update = (id, changes) => {
        save(
            queueRef.current.map((item) =>
                item.id === id ? { ...item, ...changes } : item,
            ),
        );
    };

    const remove = (id) => {
        save(queueRef.current.filter((item) => item.id !== id));
    };

    const finish = () => {
        doneRef.current?.();
    };

    const add = (files, upload) => {
        const item = {
            id: crypto.randomUUID(),
            name: getName(files),
            status: "queued",
            owner: TAB_ID,
        };

        save([...queueRef.current, item]);

        void navigator.locks
            .request(
                "personal-drive-upload",
                () =>
                    new Promise((done) => {
                        if (cancelledIdsRef.current.has(item.id)) {
                            done();
                            return;
                        }

                        doneRef.current = done;
                        update(item.id, { status: "uploading" });

                        const onProgress = (progress) => {
                            if (progress.percentage == null) return;

                            update(item.id, {
                                progress: Math.round(progress.percentage),
                                status:
                                    progress.percentage >= 100
                                        ? "processing"
                                        : "uploading",
                            });
                        };
                        const onCancelToken = (token) => {
                            cancelTokensRef.current.set(item.id, token);
                        };

                        upload(files, onProgress, onCancelToken);
                    }),
            )
            .finally(() => {
                doneRef.current = null;
                cancelTokensRef.current.delete(item.id);
                cancelledIdsRef.current.delete(item.id);
                remove(item.id);
            });
    };

    const canCancel = (item) =>
        item.owner === TAB_ID &&
        (item.status === "queued" || item.status === "uploading");

    const cancel = (id) => {
        const item = queueRef.current.find((entry) => entry.id === id);
        if (!item || !canCancel(item)) return;

        if (item.status === "queued") {
            cancelledIdsRef.current.add(id);
            remove(id);
            return;
        }

        cancelTokensRef.current.get(id)?.cancel();
    };

    useEffect(() => {
        void navigator.locks.query().then(({ held }) => {
            if (held.some((lock) => lock.name === "personal-drive-upload")) {
                return;
            }

            save([]);
        });
    }, []);

    useEffect(() => {
        const sync = (event) => {
            if (event.key !== STORAGE_KEY) return;

            queueRef.current = readQueue();
            setItems(queueRef.current);
        };

        window.addEventListener("storage", sync);

        return () => window.removeEventListener("storage", sync);
    }, []);

    return { add, cancel, canCancel, finish, items };
};

export default useUploadQueue;
