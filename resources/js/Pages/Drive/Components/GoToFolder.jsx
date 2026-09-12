import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import useClickOutside from "@/Pages/Drive/Hooks/useClickOutside.jsx";

const PAGE = 10;

// Ctrl+G quick folder navigation: type, arrow / page up-down, Enter opens.
// Matches come from the shared file-search endpoint filtered to folders.
export default function GoToFolder() {
    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [matches, setMatches] = useState([]);
    const [active, setActive] = useState(0);
    const boxRef = useRef(null);
    const inputRef = useRef(null);
    const activeRef = useRef(null);

    useClickOutside(boxRef, () => setIsOpen(false));

    useEffect(() => {
        const onKey = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "g") {
                e.preventDefault();
                setIsOpen(true);
            }
        };
        document.addEventListener("keydown", onKey);
        return () => document.removeEventListener("keydown", onKey);
    }, []);

    useEffect(() => {
        if (isOpen) {
            setQuery("");
            setMatches([]);
            setActive(0);
            inputRef.current?.focus();
        }
    }, [isOpen]);

    // Debounced folder search; query cannot contain "/" (server rule).
    useEffect(() => {
        const q = query.trim();
        if (!q) {
            setMatches([]);
            return;
        }
        const t = setTimeout(() => {
            axios
                .get("/search-folders", { params: { query: q } })
                .then((res) => {
                    setMatches(res.data);
                    setActive(0);
                })
                .catch(() => {});
        }, 150);
        return () => clearTimeout(t);
    }, [query]);

    useEffect(() => {
        activeRef.current?.scrollIntoView({ block: "nearest" });
    }, [active]);

    const openFolder = (path) => {
        setIsOpen(false);
        router.visit(
            "/drive/" + path.split("/").map(encodeURIComponent).join("/"),
        );
    };

    const move = (delta) =>
        setActive((i) => Math.max(0, Math.min(i + delta, matches.length - 1)));

    const onKeyDown = (e) => {
        const keys = {
            ArrowDown: 1,
            ArrowUp: -1,
            PageDown: PAGE,
            PageUp: -PAGE,
        };
        if (e.key in keys) {
            e.preventDefault();
            move(keys[e.key]);
        } else if (e.key === "Enter") {
            e.preventDefault();
            if (matches[active]) {
                openFolder(matches[active]);
            }
        } else if (e.key === "Escape") {
            setIsOpen(false);
        }
    };

    if (!isOpen) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 pt-24">
            <div
                ref={boxRef}
                className="w-full max-w-lg rounded-md bg-gray-800 p-2 shadow-lg ring-1 ring-black/20"
            >
                <input
                    ref={inputRef}
                    type="text"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder="Go to folder…"
                    className="w-full rounded-md border border-gray-600 bg-gray-700 p-2 text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                {matches.length > 0 && (
                    <ul className="mt-2 max-h-80 overflow-y-auto">
                        {matches.map((p, i) => {
                            const cut = p.lastIndexOf("/");
                            const parent =
                                cut === -1 ? "" : p.slice(0, cut + 1);
                            const leaf = cut === -1 ? p : p.slice(cut + 1);
                            return (
                                <li
                                    key={p}
                                    ref={i === active ? activeRef : null}
                                    onMouseEnter={() => setActive(i)}
                                    onClick={() => openFolder(p)}
                                    className={`cursor-pointer truncate rounded px-2 py-1 text-sm ${i === active ? "bg-blue-700 text-white" : "text-gray-200"}`}
                                >
                                    <span className="text-gray-400">
                                        {parent}
                                    </span>
                                    <span className="font-medium">{leaf}</span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </div>
    );
}
