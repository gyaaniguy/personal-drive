import React, { useEffect, useRef, useState } from "react";
import { usePage } from "@inertiajs/react";

const AlertBox = React.memo(function AlertBox({ message, alertStatus = true }) {
    let icon;
    let bgStatus = "bg-gray-500";
    let { flash, errors } = usePage().props;
    const [alertBoxData, setAlertBoxData] = useState(flash);
    const shownFlash = useRef(null);
    const shownErrors = useRef(null);
    const shownMessage = useRef(null);

    // Effect to update alertBoxData when props change
    useEffect(() => {
        const hasErrors = Object.keys(errors).length > 0;
        const hasNewServerAlert =
            (flash.message && shownFlash.current !== flash) ||
            (hasErrors && shownErrors.current !== errors);

        if (hasNewServerAlert) {
            const nextAlert = { ...flash };

            if (hasErrors) {
                nextAlert.message = Object.values(errors).flat().join(", ");
                nextAlert.status = false;
            }

            setAlertBoxData(nextAlert);
            shownFlash.current = flash;
            shownErrors.current = errors;
        } else if (message && shownMessage.current !== message) {
            setAlertBoxData({ message, status: alertStatus });
            shownMessage.current = message;
        }
    }, [flash, errors, message, alertStatus]);

    // Auto-dismiss: only restarts when the displayed message text changes
    useEffect(() => {
        if (!alertBoxData.message) return;
        const timer = setTimeout(() => {
            setAlertBoxData({ message: "", status: true });
        }, 10000);
        return () => clearTimeout(timer);
    }, [alertBoxData.message]);

    switch (alertBoxData.status) {
        case false:
            bgStatus = "bg-error";
            icon = (
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="stroke-current shrink-0 h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            );
            break;
        case true:
            bgStatus = "bg-success";
            icon = (
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="stroke-current shrink-0 h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            );
            break;
        case "warning":
            bgStatus = "bg-warning";

            icon = (
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="stroke-current shrink-0 h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
            );
            break;
        case "info":
            bgStatus = "bg-info";

            icon = (
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    className="stroke-current shrink-0 w-6 h-6"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>
                </svg>
            );
            break;
        default:
            icon = (
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    className="stroke-info shrink-0 w-6 h-6"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    ></path>
                </svg>
            );
    }
    return (
        alertBoxData.message && (
            <div
                role="alert"
                className={`-mt-16  fixed  left-1/2 -translate-x-1/2
             rounded-lg  text-gray-700 flex  p-3 px-5 ${bgStatus}  z-50
             ${alertBoxData.message ? "opacity-100 translate-y-0 scale-100" : "opacity-0 -translate-y-4 scale-95"}`}
            >
                {icon}
                <span className="ml-2">{alertBoxData.message}</span>
            </div>
        )
    );
});

export default AlertBox;
