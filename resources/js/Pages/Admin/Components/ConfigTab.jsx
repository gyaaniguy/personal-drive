import { router } from "@inertiajs/react";
import RefreshButton from "@/Pages/Drive/Components/RefreshButton.jsx";
import ToggleRow from "@/Components/ToggleRow.jsx";
import { useLocalStorageToggle } from "@/Pages/Drive/Hooks/useLocalStorageToggle.jsx";
import ToggleTwoFactorModal from "@/Pages/Admin/Components/ToggleTwoFactorModal.jsx";
import { useState } from "react";

export default function ConfigTab({
    storage_path,
    php_max_upload_size,
    php_post_max_size,
    php_max_file_uploads,
    setupMode,
    twoFactorStatus,
    show_two_factor_option,
}) {
    const [formData, setFormData] = useState({
        storage_path:
            storage_path || "/var/www/html/personal-drive-storage-folder",
        php_max_upload_size,
        php_post_max_size,
        php_max_file_uploads,
    });
    const [isTwoFaModalOpen, setIsTwoFaModalOpen] = useState(false);
    const [videoAutoplay, toggleVideoAutoplay] =
        useLocalStorageToggle("videoAutoplay");
    const [audioAutoplay, toggleAudioAutoplay] =
        useLocalStorageToggle("audioAutoplay");
    const [audioSavePos, toggleAudioSavePos] =
        useLocalStorageToggle("audioSavePosition");

    function handleChange(e) {
        setFormData((old) => ({
            ...old,
            [e.target.name]: e.target.value,
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        router.post("/admin-config/update", formData);
    }

    return (
        <>
            <form
                className="flex flex-col justify-between gap-y-3 md:gap-y-6"
                onSubmit={handleSubmit}
            >
                <div className="space-y-4">
                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                        Storage Path
                    </h2>
                    <div className="bg-slate-900/50 p-2 md:p-4 rounded-lg border border-blue-900/30">
                        <p className="mb-4">
                            Set the local folder where your files will be
                            stored.
                        </p>
                        <div className="flex items-center gap-2 bg-blue-950 p-0 md:p-2 rounded border border-blue-800">
                            <span className="text-blue-400 hidden md:inline">
                                📁
                            </span>
                            <input
                                className="bg-transparent w-full text-gray-300 outline-none border-0"
                                value={formData.storage_path}
                                onChange={handleChange}
                                name="storage_path"
                                id="storage_path"
                            />
                        </div>
                        <ul className="mt-4 space-y-1 text-xs text-gray-400">
                            <li>• Root directory for all application data</li>
                            <li>
                                • Changing this{" "}
                                <span className="text-orange-400 font-bold">
                                    will not move
                                </span>{" "}
                                existing files
                            </li>
                            <li>
                                • <span className="text-red-400">Warning:</span>{" "}
                                All active shares will be reset
                            </li>
                        </ul>
                        <div className="flex justify-center mt-3 md:mt-6">
                            <button className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 active:bg-blue-800">
                                {setupMode
                                    ? "Set Root Folder"
                                    : "Update Settings"}
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            {show_two_factor_option && (
                <div>
                    <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                        Security
                    </h2>
                    <ToggleTwoFactorModal
                        isTwoFaModalOpen={isTwoFaModalOpen}
                        setIsTwoFaModalOpen={setIsTwoFaModalOpen}
                        twoFactorStatus={twoFactorStatus}
                    />
                    <div className="flex items-center justify-between w-full max-w-sm py-3">
                        <span className="text-gray-200 text-lg md:font-semibold">
                            Two factor authentication
                        </span>
                        <button
                            onClick={() => setIsTwoFaModalOpen(true)}
                            className={`px-2 md:px-3 py-1 bg-gray-700 hover:bg-gray-600 text-sm font-bold rounded border border-gray-500 whitespace-nowrap ${
                                twoFactorStatus
                                    ? "text-green-400"
                                    : "text-red-400"
                            }`}
                        >
                            {twoFactorStatus ? "ENABLED ❯" : "DISABLED ❯"}
                        </button>
                    </div>
                </div>
            )}

            <div>
                <h2 className="text-blue-200 text-2xl md:font-semibold mt-2 mb-2">
                    Media Settings
                </h2>
                <div className="flex flex-col space-y-2">
                    <ToggleRow
                        label="Autoplay Videos"
                        enabled={videoAutoplay}
                        onToggle={toggleVideoAutoplay}
                    />
                    <ToggleRow
                        label="Autoplay Audios"
                        enabled={audioAutoplay}
                        onToggle={toggleAudioAutoplay}
                    />
                    <ToggleRow
                        label="Save Position of Audios"
                        enabled={audioSavePos}
                        onToggle={toggleAudioSavePos}
                    />
                </div>
            </div>

            <div className="rounded-lg max-w-xl">
                <h2 className="text-blue-200 text-lg font-bold">
                    Refresh Database
                </h2>
                <div className="border border-blue-900/50 bg-slate-800/30 flex flex-col md:flex-row md:items-center justify-between gap-4 p-2">
                    <div>
                        <p className="text-sm text-slate-400 mt-1">
                            Full system reset: Reindexes files and regenerates
                            thumbnails.
                        </p>
                    </div>
                    <div className="flex flex-col items-end gap-2">
                        <RefreshButton />
                        <span className="text-[10px] text-red-400 font-bold uppercase tracking-wider">
                            ⚠️ Removes all shares
                        </span>
                    </div>
                </div>
            </div>
        </>
    );
}
