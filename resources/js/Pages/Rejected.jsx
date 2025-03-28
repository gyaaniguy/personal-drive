export default function Rejected({message}) {

    message = message || 'You do not have permission to view this page';
    return (
        <div className="flex items-center justify-center h-screen bg-gray-100">
            <div className="text-center">
                <h1 className="text-4xl font-bold text-red-600">Access Denied</h1>
                <p className="mt-4 text-lg text-gray-700">{message}</p>
            </div>
        </div>
    );
}