import { useState } from "react";

export function useLocalStorageBool(key, defaultValue = true) {
    const [value, setValue] = useState(() => {
        const saved = localStorage.getItem(key);
        return saved !== null ? JSON.parse(saved) : defaultValue;
    });

    return [value, setValue];
}
