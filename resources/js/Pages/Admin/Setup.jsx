import {router} from "@inertiajs/react";
import {useState} from "react";
import SetupRaw from "./Layouts/SetupRaw.jsx";


export default function Setup() {

    const [formData, setFormData] = useState({
        username: '',
        password: '',
    })

    function handleChange(e) {
        setFormData(oldValues => ({...oldValues, [e.target.id]: e.target.value}))
    }

    function handleSubmit(e) {
        e.preventDefault()
        router.post('/setup/account', formData,)
    }

    return (
        <SetupRaw>
            <p className="text-3xl font-semibold text-center uppercase ">Create the admin account</p>
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label htmlFor="username" className="block text-sm font-medium  mb-1">
                        Username
                    </label>
                    <input
                        name="username"
                        value={formData.username}
                        onChange={handleChange}
                        id="username"
                        type="text"
                        placeholder="Enter your username"
                        required
                        className="bg-gray-700 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                <div>
                    <label htmlFor="password" className="block text-sm font-medium  mb-1">
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        placeholder="Enter your password"
                        value={formData.password}
                        onChange={handleChange}
                        required
                        className="bg-gray-700 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                <button
                    type="submit"
                    className="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Create Account
                </button>
            </form>
        </SetupRaw>
    );
}