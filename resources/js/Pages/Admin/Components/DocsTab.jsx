import { useState } from "react";
import UploadLimitsDocs from "@/Pages/Admin/Components/UploadLimitsDocs.jsx";

const SUBTABS = [
    ["server", "Server"],
    ["features", "Features"],
];

const FEATURES = [
    {
        title: "Files & Folders",
        points: [
            "Browse in list or tile view; toggle the layout above the file list.",
            "Create folders, rename, and delete items.",
            "Cut and paste to move files or folders between locations.",
            "Mark items as favorites for quick access from the Favorites menu.",
        ],
    },
    {
        title: "Uploading",
        points: [
            "Drag files or whole folders onto the drop zone, or use the Upload menu.",
            "Uploads run through a queue dialog so you can watch progress and keep working.",
            "When a file already exists you are prompted to replace it or abort.",
            "Image and video thumbnails are generated automatically after upload.",
        ],
    },
    {
        title: "Sharing",
        points: [
            "Share files or folders as public links from the share dialog.",
            "Protect a link with a password and set an expiry (default 7 days).",
            "Pick a custom slug for a readable share URL.",
            "Review or revoke every active link from the Shares page.",
        ],
    },
    {
        title: "Search",
        points: [
            "Search by filename from the header; results show each item's folder path.",
        ],
    },
    {
        title: "Viewing files",
        points: [
            "Open images, video, audio, PDFs, text, and HTML files directly in the browser.",
        ],
    },
    {
        title: "Security",
        points: [
            "Optional two-factor authentication for admin login (enable it on the Config tab).",
            "Password-protected uploads and shares keep private files private.",
        ],
    },
    {
        title: "REST API",
        points: [
            "Generate API tokens on the REST API tab to script uploads, downloads, and listing.",
            "The full endpoint reference lives in the API documentation on that tab.",
        ],
    },
];

function FeaturesDocs() {
    return (
        <div className="space-y-8">
            <div>
                <h2 className="text-blue-200 text-2xl font-bold mb-2">
                    Site Features
                </h2>
                <p className="text-gray-300">
                    What Personal Drive can do and where to find each feature.
                </p>
            </div>
            {FEATURES.map((f) => (
                <div key={f.title}>
                    <h3 className="text-blue-200 text-lg font-bold mb-3">
                        {f.title}
                    </h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-300">
                        {f.points.map((p, i) => (
                            <li key={i}>{p}</li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}

export default function DocsTab({
    php_max_upload_size,
    php_post_max_size,
    php_max_file_uploads,
    server_configs = [],
}) {
    const [sub, setSub] = useState("server");
    return (
        <div className="space-y-6">
            <div className="flex gap-1">
                {SUBTABS.map(([key, label]) => (
                    <button
                        key={key}
                        onClick={() => setSub(key)}
                        className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                            sub === key
                                ? "bg-blue-900/40 text-blue-200 border border-blue-700/50"
                                : "text-gray-400 hover:text-gray-200 hover:bg-slate-800/50"
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {sub === "server" && (
                <UploadLimitsDocs
                    php_max_upload_size={php_max_upload_size}
                    php_post_max_size={php_post_max_size}
                    php_max_file_uploads={php_max_file_uploads}
                    server_configs={server_configs}
                />
            )}
            {sub === "features" && <FeaturesDocs />}
        </div>
    );
}
