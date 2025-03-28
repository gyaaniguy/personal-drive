import useSelectionUtil from "@/Pages/Drive/Hooks/useSelectionutil.jsx";
import FileBrowserSection from "@/Pages/Drive/Components/FileBrowserSection.jsx";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";
import {useState} from "react";

export default function ShareFilesGuestHome({files, path, token, slug}) {
    const {
        selectAllToggle,
        handleSelectAllToggle,
        selectedFiles,
        handlerSelectFileMemo
    } = useSelectionUtil();
    const [statusMessage, setStatusMessage] = useState('')

    return (
        <div className="max-w-7xl mx-auto  bg-gray-800 text-gray-200">
            <div className="my-12 p-5">
                <div className="rounded-md gap-x-2 flex items-start relative ">
                    <AlertBox message={statusMessage}/>
                </div>
            </div>
            <FileBrowserSection files={files} path={path} isSearch={false} token={token}
                                setStatusMessage={setStatusMessage} selectAllToggle={selectAllToggle}
                                handleSelectAllToggle={handleSelectAllToggle} selectedFiles={selectedFiles}
                                handlerSelectFile={handlerSelectFileMemo}
                                isAdmin={false} slug={slug}
                               />
        </div>
    );
}
