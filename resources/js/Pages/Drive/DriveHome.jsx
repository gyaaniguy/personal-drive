import Header from '@/Pages/Drive/Layouts/Header.jsx';
import FileBrowserSection from "@/Pages/Drive/Components/FileBrowserSection.jsx";

export default function DriveHome({ files, path, token }) {
    return (
        <>
            
                <Header />
                <div className="max-w-7xl mx-auto  bg-gray-800 text-gray-200">
                    <div className="sm:px-5 px-1">
                        <FileBrowserSection files={files} path={path} token={token}
                            isAdmin={true} />
                    </div>
                </div>
            
        </>
    )
}
