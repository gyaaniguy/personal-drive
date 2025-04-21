import { createContext, useState } from "react";

// Create the context
export const CutFilesContext = createContext();

// Create a provider component
export function CutFilesProvider({ children }) {
    const [cutFiles, setCutFiles] = useState(new Set());
    const [cutPath, setCutPath] = useState("");
    return (
        <CutFilesContext.Provider
            value={{ cutFiles, setCutFiles, cutPath, setCutPath }}
        >
            {children}
        </CutFilesContext.Provider>
    );
}
