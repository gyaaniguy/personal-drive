import { useState } from "react";
import { router } from "@inertiajs/react";
import AlertBox from "@/Pages/Drive/Components/AlertBox.jsx";

const CheckSharePassword = ({ slug }) => {
    const [password, setPassword] = useState("");
    const [error, setError] = useState(null);

    const handleSubmit = (e) => {
        e.preventDefault();

        router.post(
            "/shared-check-password",
            { slug: slug, password: password },
            {
                onError: (errors) => setError(errors.password || null),
            },
        );
    };

    return (
        <div className="max-w-7xl mx-auto p-0 m-0 bg-gray-800 min-h-screen gap-x-2 flex flex-col text-gray-300  items-center justify-center">
            <div className="space-x-2  relative  -top-12 left-1/2 left-1/2 transform -translate-x-1/2 w-full">
                <AlertBox />
            </div>
            <h1 className="text-4xl m-10">Enter Password For Share</h1>
            <form className="space-x-2 " onSubmit={handleSubmit}>
                <input
                    className="bg-gray-700 rounded"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Password"
                    required
                />
                <button
                    className="  inline-flex gap-x-1 bg-blue-700 text-white font-bold py-2 px-2 rounded hover:bg-blue-600 transition duration-300
"
                    type="submit"
                >
                    Submit
                </button>
            </form>
        </div>
    );
};

export default CheckSharePassword;
