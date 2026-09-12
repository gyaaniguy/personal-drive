import { useState } from "react";
import {
    Code2,
    Compass,
    Eye,
    FileCog,
    Share2,
    ShieldCheck,
    UploadCloud,
} from "lucide-react";
import UploadLimitsDocs from "@/Pages/Admin/Components/UploadLimitsDocs.jsx";

const SUBTABS = [
    ["features", "Features"],
    ["server", "Server"],
];

const FEATURES = [
    {
        title: "Navigating",
        icon: Compass,
        area: "use",
        goal: "Find my way around?",
        summary: "Move through folders and locate items fast",
        where: "File list & header",
        points: [
            ["Breadcrumbs", "trace and jump up the current path"],
            ["Favorites", "pin items for one-click access"],
            ["Go to folder", "jump to any folder with Ctrl+G"],
            ["Search", "filename search shows the full folder path"],
        ],
    },
    {
        title: "File operations",
        icon: FileCog,
        area: "use",
        goal: "Manage my files?",
        summary: "Organize, move, and pull files back out",
        where: "File list & toolbar",
        points: [
            ["List & tile views", "switch layout from the toolbar"],
            ["Create, rename, delete", "for both files and folders"],
            ["Cut & paste", "move items between folders"],
            ["Download", "save files or folders to your device"],
        ],
    },
    {
        title: "Uploading",
        icon: UploadCloud,
        area: "use",
        goal: "Get files in?",
        summary: "Drag files or folders in, or use the Upload menu",
        where: "Drop zone / Upload menu",
        points: [
            ["Drag & drop", "drop files or entire folders onto the page"],
            ["Upload menu", "or pick files through the toolbar"],
            ["Duplicate handling", "detects existing files; replace or abort"],
            ["Auto thumbnails", "generated for images and video after upload"],
        ],
    },
    {
        title: "Viewing files",
        icon: Eye,
        area: "use",
        goal: "Preview a file?",
        summary: "Open common file types straight in the browser",
        where: "Click any file",
        points: [
            ["In-browser viewer", "images, video, audio, PDFs, text, and HTML"],
            ["Keyboard shortcuts", "arrow keys to page, Esc to close"],
        ],
    },
    {
        title: "Sharing",
        icon: Share2,
        area: "use",
        goal: "Share with someone?",
        summary: "Publish public links with optional password and expiry",
        where: "Share dialog / Shares page",
        points: [
            ["Public links", "generate a URL for any file or folder"],
            ["Password & expiry", "lock a link; auto-expires in 7 days by default"],
            ["Custom slug", "choose a readable URL instead of a random one"],
            ["Manage links", "review or revoke active links anytime"],
        ],
    },
    {
        title: "Security",
        icon: ShieldCheck,
        area: "admin",
        goal: "Keep it private?",
        summary: "2FA for admin, plus password-protected files and shares",
        where: "Config tab",
        points: [
            ["Two-factor auth", "optional TOTP for admin login, set on Config"],
            ["Private by password", "password-locked uploads and shares stay private"],
        ],
    },
    {
        title: "REST API",
        icon: Code2,
        area: "admin",
        goal: "Automate it?",
        summary: "Script uploads, downloads, and listing with API tokens",
        where: "REST API tab",
        points: [
            ["API tokens", "create and revoke tokens for scripted access"],
            ["Endpoint reference", "full docs on the API documentation tab"],
        ],
    },
];

const SHORTCUTS = [
    ["Ctrl+G", "Go To Folder"],
    ["←", "Previous image/video in the media viewer"],
    ["→", "Next image/video in the media viewer"],
    ["Esc", "Close the media viewer or an open menu"],
    ["Ctrl + Enter", "Save changes while editing a text file"],
];

function FeatureCard({ feature }) {
    const Icon = feature.icon;
    return (
        <div className="overflow-hidden rounded-lg border border-blue-500/30 bg-gray-800/40">
            <div className="flex items-center gap-3 border-b border-white/5 border-l-2 border-l-amber-400/70 px-4 py-2.5">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-blue-500/15 text-blue-300">
                    <Icon className="h-4 w-4" />
                </span>
                <div className="min-w-0">
                    <h4 className="text-base font-semibold leading-tight text-blue-100">
                        {feature.title}
                    </h4>
                    <p className="text-sm text-gray-400">{feature.summary}</p>
                </div>
                <span className="ml-auto shrink-0 rounded bg-amber-500/15 px-2 py-0.5 text-xs font-medium text-amber-300">
                    {feature.where}
                </span>
            </div>
            <dl className="divide-y divide-white/5">
                {feature.points.map(([label, detail], i) => (
                    <div
                        key={i}
                        className="flex flex-col gap-0.5 px-4 py-2 sm:flex-row sm:gap-4"
                    >
                        <dt className="font-semibold text-gray-200 sm:w-40 sm:shrink-0">
                            {label}
                        </dt>
                        <dd className="text-gray-400">{detail}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function FeaturesDocs() {
    return (
        <div className="space-y-8">
            <div>
                <h2 className="mb-1 text-2xl font-bold text-blue-100">
                    Site Features
                </h2>
                <p className="text-gray-400">
                    What Personal Drive can do and where to find each feature.
                </p>
            </div>

            <div className="rounded-lg border border-blue-500/30 bg-gray-800/40">
                <h3 className="border-l-2 border-amber-400/70 bg-blue-500/10 px-4 py-3 text-lg font-bold text-blue-100">
                    Keyboard Shortcuts
                </h3>
                <table className="w-full text-left text-gray-300">
                    <tbody className="divide-y divide-white/5">
                        {SHORTCUTS.map(([keys, desc]) => (
                            <tr key={keys} className="align-middle">
                                <td className="py-2 pl-4 pr-4 whitespace-nowrap">
                                    <kbd className="inline-block rounded border border-gray-500 bg-gray-800 px-2 py-0.5 font-mono text-sm text-blue-200">
                                        {keys}
                                    </kbd>
                                </td>
                                <td className="py-2 pr-4 text-sm">{desc}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="space-y-4">
                {FEATURES.map((f) => (
                    <FeatureCard key={f.title} feature={f} />
                ))}
            </div>
        </div>
    );
}

export default function DocsTab({
    php_max_upload_size,
    php_post_max_size,
    php_max_file_uploads,
    server_configs = [],
}) {
    const [sub, setSub] = useState("features");
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
