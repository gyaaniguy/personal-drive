import { useEffect, useLayoutEffect, useRef, useState } from "react";
import useClickOutside from "@/Pages/Drive/Hooks/useClickOutside.jsx";

const DropdownMenu = ({
    label,
    icon,
    ariaLabel,
    panelId,
    width = "w-32",
    children,
}) => {
    const [isOpen, setIsOpen] = useState(false);
    const [alignRight, setAlignRight] = useState(false);
    const menuRef = useRef(null);
    const triggerRef = useRef(null);
    const panelRef = useRef(null);

    useClickOutside(menuRef, () => setIsOpen(false));

    const close = () => setIsOpen(false);
    const focusTrigger = () => triggerRef.current?.focus();

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const handleKeyDown = (event) => {
            if (event.key !== "Escape") {
                return;
            }

            close();
            focusTrigger();
        };

        document.addEventListener("keydown", handleKeyDown);

        return () => document.removeEventListener("keydown", handleKeyDown);
    }, [isOpen]);

    // Left-align to the trigger by default; flip right only when the panel
    // would overflow the viewport (e.g. trigger near the right edge).
    useLayoutEffect(() => {
        if (!isOpen || !panelRef.current) {
            return;
        }
        const { left } = triggerRef.current.getBoundingClientRect();
        const overflowsRight =
            left + panelRef.current.offsetWidth > window.innerWidth - 8;
        setAlignRight(overflowsRight);
    }, [isOpen]);

    return (
        <div className="relative mr-1" ref={menuRef}>
            <button
                ref={triggerRef}
                type="button"
                aria-expanded={isOpen}
                aria-controls={panelId}
                aria-label={ariaLabel}
                className="inline-flex min-h-7 min-w-7 items-center justify-center gap-x-1 rounded bg-blue-700 p-1 text-sm font-bold text-white hover:bg-blue-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-800 active:bg-blue-800"
                onClick={() => setIsOpen((open) => !open)}
            >
                {icon}
                {label && <span className="hidden sm:inline">{label}</span>}
            </button>

            {isOpen && (
                <div
                    ref={panelRef}
                    id={panelId}
                    className={`absolute z-20 mt-2 rounded-md bg-gray-700 p-1 text-left shadow-lg ring-1 ring-black ring-opacity-5 ${width} ${alignRight ? "right-0" : "left-0"}`}
                >
                    {typeof children === "function"
                        ? children({ close, focusTrigger })
                        : children}
                </div>
            )}
        </div>
    );
};

export default DropdownMenu;
