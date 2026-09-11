import Header from "@/Pages/Drive/Layouts/Header.jsx";
import { useState } from "react";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";
import ConfigTab from "@/Pages/Admin/Components/ConfigTab.jsx";
import ApiTokensTab from "@/Pages/Admin/Components/ApiTokensTab.jsx";
import UploadLimitsDocs from "@/Pages/Admin/Components/UploadLimitsDocs.jsx";

const TABS = [
    ["config", "Config"],
    ["tokens", "REST API"],
    ["docs", "Documentation"],
];

export default function Settings({
    storage_path,
    php_max_upload_size,
    php_post_max_size,
    php_max_file_uploads,
    setupMode,
    twoFactorStatus,
    show_two_factor_option,
    tokens = [],
    server_configs = [],
    api_sections = [],
    flash = {},
}) {
    const [activeTab, setActiveTab] = useState("config");

    function switchTab(tab) {
        setActiveTab(tab);
        const url = new URL(window.location);
        url.searchParams.set("tab", tab);
        window.history.replaceState({}, "", url);
    }

    return (
        <>
            {!setupMode && <Header />}

            <div className="p-1 sm:p-4 space-y-4 max-w-7xl mx-auto text-gray-200 bg-gray-800">
                {/* Tab bar */}
                <div className="flex gap-1 border-b border-blue-900/30 mb-6 max-w-3xl mx-auto">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => switchTab(key)}
                            className={`px-4 py-2 text-sm font-medium rounded-t-lg transition-colors ${
                                activeTab === key
                                    ? "bg-blue-900/30 text-blue-200 border-b-2 border-blue-400"
                                    : "text-gray-400 hover:text-gray-200 hover:bg-slate-800/50"
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <main className="mx-auto max-w-7xl">
                    <AlertBox />

                    <div
                        className={`max-w-3xl mx-auto min-h-[500px] flex flex-col gap-y-8 md:gap-y-12 ${
                            activeTab === "tokens"
                                ? ""
                                : "bg-blue-900/15 p-2 md:p-12"
                        }`}
                    >
                        {activeTab === "config" && (
                            <ConfigTab
                                storage_path={storage_path}
                                php_max_upload_size={php_max_upload_size}
                                php_post_max_size={php_post_max_size}
                                php_max_file_uploads={php_max_file_uploads}
                                setupMode={setupMode}
                                twoFactorStatus={twoFactorStatus}
                                show_two_factor_option={show_two_factor_option}
                            />
                        )}

                        {activeTab === "tokens" && (
                            <ApiTokensTab
                                tokens={tokens}
                                api_sections={api_sections}
                                flash={flash}
                            />
                        )}

                        {activeTab === "docs" && (
                            <div className="space-y-8">
                                <UploadLimitsDocs
                                    php_max_upload_size={php_max_upload_size}
                                    php_post_max_size={php_post_max_size}
                                    php_max_file_uploads={php_max_file_uploads}
                                    server_configs={server_configs}
                                />
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
