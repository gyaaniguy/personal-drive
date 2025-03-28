import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";

export default function SetupRaw({children}) {
    return (
        <>
            <div className="p-4 space-y-4 max-w-7xl mx-auto text-gray-300">
                <h2 className="text-center text-5xl my-12 mb-32">PersonalDrive Setup</h2>
                <main className="mx-auto max-w-7xl bg-blue-900/15 min-h-[500px]">
                    <AlertBox/>
                    <div className="w-[700px] mx-auto p-12 flex flex-col gap-y-20">
                        {children}
                    </div>
                </main>
            </div>
        </>
    );
}