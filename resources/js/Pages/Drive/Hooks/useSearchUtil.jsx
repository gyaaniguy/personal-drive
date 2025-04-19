import { router } from "@inertiajs/react";

function useSearchUtil() {
    async function handleSearch(e, searchText) {
        e.preventDefault();
        router.post(
            "/search-files",
            { query: searchText },
            {
                onSuccess: () => {},
            },
        );
    }

    return { handleSearch };
}

export default useSearchUtil;
