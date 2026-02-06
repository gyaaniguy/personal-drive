import { useState } from "react";

export default function SearchBar({ handleSearch }) {
    const [searchValue, setSearchValue] = useState("");
    const clearSearch = () => {
        setSearchValue("");
    };
    return (
        <div>
            <form className="flex space-x-2 items-center text-gray-300">
                <div className="relative">
                    <input
                        type="text"
                        id="searchbox"
                        className="border border-gray-300 rounded-md p-1 md:p-2 sm:pr-8 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-700 w-20 sm:w-44 md:w-52"
                        placeholder="&#128270;"
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                    />
                    {searchValue && (
                        <button
                            type="button"
                            onClick={clearSearch}
                            className="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 p-1 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            &#x2715;
                        </button>
                    )}
                </div>

                <button
                    onClick={(e) => handleSearch(e, searchValue)}
                    className="bg-blue-700 text-gray-200 font-bold p-1 md:p-2 text-sm md:text-base rounded hover:bg-blue-600 active:bg-blue-800"
                >
                    Search
                </button>
            </form>
        </div>
    );
}
