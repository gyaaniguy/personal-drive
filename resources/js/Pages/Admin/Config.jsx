import Header from "@/Pages/Drive/Layouts/Header.jsx";
import {router} from "@inertiajs/react";
import {useState} from "react";
import RefreshButton from "@/Pages/Drive/Components/RefreshButton.jsx";
import ToggleRow from "@/Components/ToggleRow.jsx";
import {useLocalStorageBool} from "@/Pages/Drive/Hooks/useLocalStorageBool.jsx";
import CreateItemModal from "@/Pages/Drive/Components/CreateFolderModal.jsx";
import ToggleTwoFactorModal from "@/Pages/Admin/Components/ToggleTwoFactorModal.jsx";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";

export default function AdminConfig({
                                        storage_path,
                                        php_max_upload_size,
                                        php_post_max_size,
                                        php_max_file_uploads,
                                        setupMode,
                                        twoFactorStatus
                                    }) {
    const [formData, setFormData] = useState({
        storage_path:
            storage_path || "/var/www/html/personal-drive-storage-folder",
        php_max_upload_size: php_max_upload_size,
        php_post_max_size: php_post_max_size,
        php_max_file_uploads: php_max_file_uploads,
    });
    const [isTwoFaModalOpen, setIsTwoFaModalOpen] = useState(false);
    const [videoAutoplay, setVideoAutoplay] =
        useLocalStorageBool("videoAutoplay");
    const [audioAutoplay, setAudioAutoplay] =
        useLocalStorageBool("audioAutoplay");
    const [audioSavePos, setAudioSavePos] =
        useLocalStorageBool("audioSavePosition");

    function handleChange(e) {
        setFormData((oldValues) => ({
            ...oldValues,
            [e.target.id]: e.target.value,
        }));
    }

    function handleSubmit(e) {
        e.preventDefault();
        router.post("/admin-config/update", formData);
    }

    function handleToggle2FaStatusButton() {
        setIsTwoFaModalOpen(true);
    }

    function handleVideoAutoplayToggle() {
        localStorage.setItem("videoAutoplay", JSON.stringify(!videoAutoplay));
        setVideoAutoplay(!videoAutoplay);
    }

    function handleAudioAutoplayToggle() {
        localStorage.setItem("audioAutoplay", JSON.stringify(!audioAutoplay));
        setAudioAutoplay(!audioAutoplay);
    }

    function handleAudioSavePosToggle() {
        localStorage.setItem(
            "audioSavePosition",
            JSON.stringify(!audioSavePos),
        );
        setAudioSavePos(!audioSavePos);
    }

    return (
        <>
            {!setupMode && <Header/>}

            <div className="p-1 sm:p-4 space-y-4 max-w-7xl mx-auto text-gray-200  bg-gray-800 ">
                <h2 className="text-center text-4xl font-semibold text-gray-300 my-12 mb-20">
                    Admin Settings
                </h2>
                <main className="mx-auto max-w-7xl ">
                    <AlertBox/>




                    <div className="max-w-3xl mx-auto bg-blue-900/15 p-2 md:p-12 min-h-[500px] flex flex-col gap-y-8 md:gap-y-20 ">
                        <form
                            className="flex flex-col justify-between gap-y-3 md:gap-y-6"
                            onSubmit={handleSubmit}
                        >
                            <div className="space-y-4">

                                <h2 className=" text-blue-200 text-2xl font-bold mt-2 mb-2 ">Storage Path</h2>

                                <div className="bg-slate-900/50 p-2 md:p-4 rounded-lg border border-blue-900/30">

                                    <p className=" mb-4 ">
                                        Set the local folder where your
                                        files will be stored.
                                    </p>
                                    <div className="flex items-center gap-2 bg-blue-950 p-0 md:p-2 rounded border border-blue-800">
                                        <span className="text-blue-400 hidden md:inline">📁</span>
                                        <input
                                            className="bg-transparent w-full text-gray-300 outline-none border-0"
                                            value={formData.storage_path}
                                            onChange={handleChange}
                                        />
                                    </div>
                                    <ul className="mt-4 space-y-1 text-xs text-gray-400">
                                        <li>• Root directory for all application data</li>
                                        <li>• Changing this <span className="text-orange-400 font-bold">will not move</span> existing files</li>
                                        <li>• <span className="text-red-400">Warning:</span> All active shares will be reset</li>
                                    </ul>


                                    <div className="flex justify-center mt-3 md:mt-6">
                                        <button
                                            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 active:bg-blue-800 ">
                                            {setupMode && "Set Root Folder"}
                                            {!setupMode && "Update Settings"}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div>
                            <h2 className=" text-blue-200 text-2xl font-bold mt-2 mb-2 ">
                                Security
                            </h2>
                            <ToggleTwoFactorModal
                                isTwoFaModalOpen={isTwoFaModalOpen}
                                setIsTwoFaModalOpen={setIsTwoFaModalOpen}
                                twoFactorStatus={twoFactorStatus}
                            />
                            <div className="flex items-center justify-between w-full max-w-sm py-3">
                                <span className="text-gray-200 text-lg md:font-semibold">Two factor authentication</span>

                                {twoFactorStatus &&
                                    <button
                                        onClick={handleToggle2FaStatusButton}
                                        className="px-2 md:px-3 py-1 bg-gray-700 hover:bg-gray-600 text-green-400 text-sm font-bold rounded border border-gray-500"
                                    >
                                        ENABLED ❯
                                    </button>
                                }
                                {!twoFactorStatus &&
                                    <button
                                        onClick={handleToggle2FaStatusButton}
                                        className="px-2 md:px-3 py-1 bg-gray-700 hover:bg-gray-600 text-red-400 text-sm font-bold rounded border border-gray-500"
                                    >
                                        DISABLED ❯
                                    </button>
                                }
                            </div>
                        </div>
                        <div>
                            <h2 className=" text-blue-200 text-2xl font-bold mt-2 mb-2 ">
                                Media Settings
                            </h2>
                            <div className="flex flex-col space-y-2">
                                <ToggleRow
                                    label="Autoplay Videos"
                                    enabled={videoAutoplay}
                                    onToggle={handleVideoAutoplayToggle}
                                />
                                <ToggleRow
                                    label="Autoplay Audios"
                                    enabled={audioAutoplay}
                                    onToggle={handleAudioAutoplayToggle}
                                />
                                <ToggleRow
                                    label="Save Position of Audios"
                                    enabled={audioSavePos}
                                    onToggle={handleAudioSavePosToggle}
                                />
                            </div>
                        </div>


                        <div className="rounded-lg max-w-xl">
                            <h2 className="text-blue-200 text-lg font-bold">Refresh Database</h2>

                            <div className="border border-blue-900/50 bg-slate-800/30 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm text-slate-400 mt-1 p-2">
                                        Full system reset: Reindexes files and regenerates thumbnails.
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

                        <div className="overflow-x-scroll">
                            <h2 className=" text-blue-200 text-2xl font-bold mt-2 mb-2 ">
                                Increase upload limits
                            </h2>
                            <p className=" mb-6 ">
                                PHP OR your webserver default upload limits are
                                too small for most people.{" "}
                            </p>

                            <p className=" text-blue-200 text-lg font-bold mt-10 mb-5  ">
                                Current Server PHP Upload Size Limits
                            </p>
                            <div className=" mb-0 flex  mx-auto items-baseline gap-x-2 w-full">
                                <p className="  font-bold ">Max upload size:</p>
                                <p className="text-lg text-gray-200 text-right mt-1">
                                    {formData.php_max_upload_size}
                                </p>
                            </div>
                            <div className=" flex  mx-auto items-baseline gap-x-2 w-full">
                                <p className="  font-bold ">
                                    Post upload size:
                                </p>
                                <p className="text-lg text-gray-200 text-right mt-1">
                                    {formData.php_post_max_size}
                                </p>
                            </div>
                            <div className=" flex  mx-auto items-baseline gap-x-2 w-full">
                                <p className="  font-bold ">
                                    Max File Uploads:
                                </p>
                                <p className="text-lg text-gray-200 text-right mt-1">
                                    {formData.php_max_file_uploads}
                                </p>
                            </div>

                            <p className="text-lg text-blue-200 mt-10 mb-5 font-bold">
                                Instructions for various apps :
                            </p>
                            <div className="flex flex-col text-gray-300 ">
                                <div>
                                    <span className="font-bold text-lg text-gray-100">
                                        {" "}
                                        php-fpm:
                                    </span>{" "}
                                    Edit the www.conf file
                                    <pre className="mt-1 mb-5 text-sm text-gray-400">
                                        {`php_value[upload_max_filesize] = 1G
php_value[post_max_size] = 1G
php_value[max_file_uploads] = 1000`}
                                    </pre>
                                </div>
                                <div>
                                    <span className="font-bold text-lg text-gray-100">
                                        {" "}
                                        PHP:
                                    </span>{" "}
                                    Edit 3 variables in php.ini file
                                    <pre className="mt-1 mb-5 text-sm text-gray-400">
                                        {`upload_max_filesize = 1G
post_max_size = 1G
max_file_uploads = 10000`}
                                    </pre>
                                </div>
                                <div>
                                    <span className="font-bold text-lg text-gray-100">
                                        {" "}
                                        apache:
                                    </span>{" "}
                                    edit the .htaccess file in /public
                                    <pre className="mt-1 mb-5 text-sm text-gray-400">
                                        {`php_value upload_max_filesize 64M
php_value post_max_size 64M
php_value max_file_uploads 10000`}
                                    </pre>
                                </div>
                                <div>
                                    <span className="font-bold text-lg text-gray-100">
                                        {" "}
                                        nginx:
                                    </span>{" "}
                                    Increase client_max_body_size param
                                    <pre className="mt-1 mb-5 text-sm text-gray-400">
                                        {`http {
    client_max_body_size 1000M;
}`}
                                    </pre>
                                </div>
                                <div>
                                    <span className="font-bold text-lg text-gray-100">
                                        {" "}
                                        Caddy:
                                    </span>{" "}
                                    Increase request_timeout param
                                    <pre className="mt-1 mb-5 text-sm text-gray-400">
                                        {`demo.personaldrive.xyz {
    root * /some/folder
    php_fastcgi unix/{{ php_fpm_socket.stdout }}
    file_server
    request_body {
        max_size 1G
        timeout 1000s
    }
}`}
                                    </pre>
                                </div>
                            </div>
                        </div>

                    </div>
                </main>
            </div>
        </>
    );
}
