import Header from "@/Pages/Drive/Layouts/Header.jsx";
import FileBrowserSection from "@/Pages/Drive/Components/FileBrowserSection.jsx";

export default function DriveHome({
    files,
    path,
    token,
    folderExists,
    favorites = [],
}) {
    return (
        <>
            <Header />
            <div className="max-w-7xl mx-auto bg-gray-800 text-gray-200 px-1 md:px-0">
                <div className="md:px-5">
                    <FileBrowserSection
                        files={files}
                        path={path}
                        token={token}
                        isAdmin={true}
                        favorites={favorites}
                        folderExists={folderExists}
                    />
                </div>
            </div>
        </>
    );
}
