import Header from "@/Pages/Drive/Layouts/Header.jsx";
import { router } from "@inertiajs/react";
import { useState } from "react";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";
import RefreshButton from "@/Pages/Drive/Components/RefreshButton.jsx";

export default function AdminConfig({
    storage_path,
    php_max_upload_size,
    php_post_max_size,
    php_max_file_uploads,
    setupMode,
}) {
    const [formData, setFormData] = useState({
        storage_path:
            storage_path || "/var/www/html/personal-drive-storage-folder",
        php_max_upload_size: php_max_upload_size,
        php_post_max_size: php_post_max_size,
        php_max_file_uploads: php_max_file_uploads,
    });

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

    return (
        <>
            {!setupMode && <Header />}

            <div className="p-1 sm:p-4 space-y-4 max-w-7xl mx-auto text-gray-200  bg-gray-800 ">
                <h2 className="text-center text-4xl font-semibold text-gray-300 my-12 mb-32">
                    Admin Settings
                </h2>
                <main className="mx-auto max-w-7xl ">
                    <AlertBox />

                    <div className="max-w-3xl mx-auto bg-blue-900/15 p-4 md:p-12 min-h-[500px] flex flex-col gap-y-20 ">
                        <form
                            className="flex flex-col justify-between gap-y-6"
                            onSubmit={handleSubmit}
                        >
                            <div>
                                <div className="m-1 flex flex-col mx-auto items-start gap-y-5 w-full">
                                    <label
                                        htmlFor="storage_path"
                                        className="block text-blue-200 text-xl font-bold "
                                    >
                                        Storage Path:
                                    </label>
                                    <p className="  mt-1">
                                        <p className=" mb-1 ">
                                            Set the local folder where your
                                            files will be stored.
                                        </p>
                                        <p className="text-sm text-gray-400">
                                            - Root Path of all files
                                            <br />
                                            - Changing will NOT move files !
                                            <br />
                                            - Files will be stored in a
                                            subFolder
                                            <br />- All Shares will get reset !
                                        </p>
                                    </p>

                                    <input
                                        type="text"
                                        id="storage_path"
                                        name="storage_path"
                                        value={formData.storage_path}
                                        onChange={handleChange}
                                        className="w-full  text-gray-200 bg-blue-900 border border-blue-900 rounded-md focus:border-indigo-500 focus:ring-indigo-500 "
                                    />
                                </div>
                            </div>
                            <div className="flex justify-center mt-1">
                                <button className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 active:bg-blue-800 ">
                                    {setupMode && "Set Root Folder"}
                                    {!setupMode && "Update Settings"}
                                </button>
                            </div>
                        </form>
                        <div>
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
                            <div className="flex flex-col text-gray-300">
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

                        <div className="  ">
                            <h2 className=" text-blue-200 text-xl font-bold mb-2 ">
                                Refresh Database and cancel all Shares{" "}
                            </h2>
                            <p className="mb-4">
                                'Reset' option. This will reindex all files,
                                remove all shares, regenerate thumbnail
                            </p>
                            <RefreshButton />
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
