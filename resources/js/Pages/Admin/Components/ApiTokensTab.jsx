import { router } from "@inertiajs/react";
import { useState, useEffect } from "react";
import ApiDocs from "./ApiDocs.jsx";

export default function ApiTokensTab({ tokens, api_sections, flash }) {
    const [showTokenModal, setShowTokenModal] = useState(false);
    const [createdToken, setCreatedToken] = useState(null);
    const [tokenName, setTokenName] = useState("");
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (flash?.plain_text_token) {
            setCreatedToken(flash.plain_text_token);
            setShowTokenModal(true);
        }
    }, [flash]);

    function handleCreate(e) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.post(
            route("admin.api-tokens.store"),
            { name: tokenName },
            {
                onSuccess: (page) => {
                    const plain = page.props.flash?.plain_text_token;
                    if (plain) {
                        setCreatedToken(plain);
                        setShowTokenModal(true);
                        setTokenName("");
                    }
                    setProcessing(false);
                },
                onError: (err) => {
                    setErrors(err);
                    setProcessing(false);
                },
            },
        );
    }

    function handleDelete(tokenId) {
        if (!confirm("Are you sure you want to delete this token?")) return;
        router.delete(route("admin.api-tokens.destroy", tokenId));
    }

    function copyToken() {
        navigator.clipboard.writeText(createdToken);
    }

    return (
        <>
            {/* Create Token */}
            <div>
                <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                    Create Token
                </h2>
                <div className="bg-slate-900/50 p-2 md:p-4 rounded-lg border border-blue-900/30">
                    <form
                        className="flex items-center gap-3"
                        onSubmit={handleCreate}
                    >
                        <input
                            type="text"
                            value={tokenName}
                            onChange={(e) => setTokenName(e.target.value)}
                            placeholder="Token name (e.g. 'my-app')"
                            className="flex-1 bg-blue-950 p-2 rounded border border-blue-800 text-gray-300 outline-none"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 active:bg-blue-800 disabled:opacity-50"
                        >
                            Create
                        </button>
                    </form>
                    {errors.name && (
                        <p className="text-red-400 text-sm mt-2">
                            {errors.name}
                        </p>
                    )}
                </div>
            </div>

            {/* Existing Tokens */}
            <div>
                <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                    Existing Tokens
                </h2>
                <div className="bg-slate-900/50 p-2 md:p-4 rounded-lg border border-blue-900/30">
                    {tokens.length === 0 ? (
                        <p className="text-gray-400 text-sm">
                            No API tokens yet.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-blue-900/30 text-gray-400">
                                        <th className="text-left py-2 pr-4">
                                            Name
                                        </th>
                                        <th className="text-left py-2 pr-4">
                                            Created
                                        </th>
                                        <th className="text-left py-2 pr-4">
                                            Last Used
                                        </th>
                                        <th className="py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tokens.map((token) => (
                                        <tr
                                            key={token.id}
                                            className="border-b border-blue-900/20"
                                        >
                                            <td className="py-2 pr-4 text-gray-200">
                                                {token.name}
                                            </td>
                                            <td className="py-2 pr-4 text-gray-400">
                                                {new Date(
                                                    token.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="py-2 pr-4 text-gray-400">
                                                {token.last_used_at
                                                    ? new Date(
                                                          token.last_used_at,
                                                      ).toLocaleDateString()
                                                    : "Never"}
                                            </td>
                                            <td className="py-2">
                                                <button
                                                    onClick={() =>
                                                        handleDelete(token.id)
                                                    }
                                                    className="px-2 py-1 bg-red-900/50 hover:bg-red-800 text-red-300 text-xs rounded border border-red-800"
                                                >
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* API Documentation */}
            <div>
                <h2 className="text-blue-200 text-2xl font-bold mt-2 mb-2">
                    API Documentation
                </h2>
                <ApiDocs sections={api_sections} />
            </div>

            {/* One-time Token Display Modal */}
            {showTokenModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                    <div className="bg-gray-900 border border-blue-900/50 rounded-lg p-6 max-w-md w-full mx-4">
                        <h3 className="text-lg font-semibold text-gray-200 mb-2">
                            Token Created
                        </h3>
                        <p className="text-gray-400 text-sm mb-4">
                            Copy this token now. You won't be able to see it
                            again.
                        </p>
                        <div className="bg-blue-950 p-3 rounded border border-blue-800 text-gray-300 text-sm font-mono break-all mb-4">
                            {createdToken}
                        </div>
                        <div className="flex gap-3">
                            <button
                                onClick={copyToken}
                                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                Copy
                            </button>
                            <button
                                onClick={() => {
                                    setShowTokenModal(false);
                                    setCreatedToken(null);
                                }}
                                className="px-4 py-2 bg-gray-700 text-gray-300 rounded-md hover:bg-gray-600"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
