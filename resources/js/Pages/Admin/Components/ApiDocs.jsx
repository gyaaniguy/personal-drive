import { useState, useEffect, useRef } from "react";

const METHOD_COLORS = {
    GET: "text-green-400 bg-green-900/40 border-green-700/50",
    POST: "text-blue-400 bg-blue-900/40 border-blue-700/50",
    DELETE: "text-red-400 bg-red-900/40 border-red-700/50",
};

function MethodBadge({ method }) {
    return (
        <span
            className={`inline-block font-mono text-xs font-bold px-2 py-0.5 rounded border ${METHOD_COLORS[method]}`}
        >
            {method}
        </span>
    );
}

function ParamsTable({ params, title }) {
    if (!params || params.length === 0) return null;
    return (
        <div>
            <h4 className="text-gray-300 text-sm font-semibold mb-2">
                {title}
            </h4>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-blue-900/30 text-gray-400">
                            <th className="text-left py-1 pr-4">Name</th>
                            <th className="text-left py-1 pr-4">Type</th>
                            <th className="text-left py-1 pr-4">Required</th>
                            <th className="text-left py-1">Description</th>
                        </tr>
                    </thead>
                    <tbody className="text-gray-300">
                        {params.map((p, i) => (
                            <tr key={i} className="border-b border-blue-900/20">
                                <td className="py-1.5 pr-4 font-mono text-xs text-blue-300">
                                    {p.name}
                                </td>
                                <td className="py-1.5 pr-4 font-mono text-xs text-gray-400">
                                    {p.type}
                                </td>
                                <td className="py-1.5 pr-4">
                                    {p.required ? (
                                        <span className="text-yellow-400 text-xs">
                                            required
                                        </span>
                                    ) : (
                                        <span className="text-gray-500 text-xs">
                                            optional
                                        </span>
                                    )}
                                </td>
                                <td className="py-1.5 text-xs text-gray-400">
                                    {p.description}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

const REF_RE = /(File object|Pagination)/g;
function linkifyResponse(text) {
    return text.split(REF_RE).map((part, i) =>
        part === "File object" || part === "Pagination" ? (
            <a
                key={i}
                href={`#${epId(part)}`}
                className="text-blue-400 underline hover:text-blue-300"
            >
                {part}
            </a>
        ) : (
            part
        ),
    );
}

function EndpointCard({ endpoint }) {
    return (
        <div
            id={`ep-${endpoint.title.replace(/\s+/g, "-").toLowerCase()}`}
            className="bg-slate-900/60 border border-blue-900/30 rounded-lg p-5 space-y-4 scroll-mt-4"
        >
            <div>
                <h4 className="text-blue-200 text-base font-semibold mb-1">
                    {endpoint.title}
                </h4>
                {(endpoint.method || endpoint.url) && (
                    <div className="flex items-center gap-3 flex-wrap">
                        {endpoint.method && (
                            <MethodBadge method={endpoint.method} />
                        )}
                        {endpoint.url && (
                            <code className="text-sm text-gray-300 font-mono break-all">
                                {endpoint.url}
                            </code>
                        )}
                    </div>
                )}
            </div>

            <p className="text-gray-400 text-sm">{endpoint.description}</p>
            <ParamsTable params={endpoint.params} title="Query Parameters" />
            <ParamsTable params={endpoint.body} title="Request Body" />

            {endpoint.response && (
                <div>
                    <h4 className="text-gray-300 text-sm font-semibold mb-2">
                        Response
                    </h4>
                    <pre className="bg-blue-950 border border-blue-800 rounded p-3 text-xs text-gray-300 overflow-x-auto whitespace-pre-wrap">
                        {linkifyResponse(endpoint.response)}
                    </pre>
                </div>
            )}

            {endpoint.curl && (
                <div>
                    <h4 className="text-gray-300 text-sm font-semibold mb-2">
                        Example
                    </h4>
                    <pre className="bg-blue-950 border border-blue-800 rounded p-3 text-xs text-green-300 overflow-x-auto whitespace-pre-wrap">
                        {endpoint.curl}
                    </pre>
                </div>
            )}
        </div>
    );
}

function epId(title) {
    return `ep-${title.replace(/\s+/g, "-").toLowerCase()}`;
}

export default function ApiDocs({ sections = [] }) {
    const [openSection, setOpenSection] = useState(null);
    const clickedRef = useRef(false);

    useEffect(() => {
        const onScroll = () => {
            if (clickedRef.current) return;
            for (const section of sections) {
                const el = document.getElementById(
                    epId(section.endpoints[0]?.title),
                );
                if (el && el.getBoundingClientRect().top <= 120) {
                    setOpenSection(section.title);
                }
            }
        };
        window.addEventListener("scroll", onScroll, { passive: true });
        return () => window.removeEventListener("scroll", onScroll);
    }, [sections]);

    const toggle = (title) => {
        clickedRef.current = true;
        setTimeout(() => (clickedRef.current = false), 1000);
        const next = openSection === title ? null : title;
        setOpenSection(next);
        if (next) {
            const section = sections.find((s) => s.title === next);
            const el = document.getElementById(
                epId(section?.endpoints[0]?.title),
            );
            el?.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    };

    return (
        <div className="bg-slate-900/50 p-4 md:p-6 rounded-lg border border-blue-900/30">
            <div className="mb-6">
                <h3 className="text-blue-300 text-lg font-semibold mb-2">
                    Authentication
                </h3>
                <p className="text-gray-400 text-sm mb-2">
                    Include your token in every request:
                </p>
                <div className="bg-blue-950 p-3 rounded border border-blue-800 text-sm font-mono text-gray-300">
                    Authorization: Bearer {"<your-token>"}
                </div>
                <p className="text-gray-500 text-xs mt-2">
                    Rate limit: 100 requests per minute per token.
                </p>
            </div>

            <div className="flex gap-6 relative">
                <nav className="hidden lg:block w-44 shrink-0">
                    <div className="sticky top-4 space-y-1">
                        {sections.map((section) => (
                            <div key={section.title}>
                                <button
                                    onClick={() => toggle(section.title)}
                                    className={`w-full text-left text-sm font-semibold px-2 py-1.5 rounded transition-colors ${
                                        openSection === section.title
                                            ? "text-blue-300 bg-blue-900/20"
                                            : "text-gray-400 hover:text-gray-200 hover:bg-slate-800/50"
                                    }`}
                                >
                                    {section.title}
                                </button>
                                <div
                                    className={`grid transition-all duration-200 ${openSection === section.title ? "grid-rows-[1fr]" : "grid-rows-[0fr]"}`}
                                >
                                    <ul className="space-y-0.5 ml-1 border-l border-blue-900/40 pl-2 mt-0.5 mb-2 overflow-hidden">
                                        {section.endpoints.map((ep) => (
                                            <li key={ep.title}>
                                                <a
                                                    href={`#${epId(ep.title)}`}
                                                    className="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-200 py-0.5"
                                                >
                                                    {ep.method && (
                                                        <MethodBadge
                                                            method={ep.method}
                                                        />
                                                    )}
                                                    <span>{ep.title}</span>
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        ))}
                    </div>
                </nav>

                <div className="flex-1 min-w-0 space-y-8">
                    {sections.map((section) => (
                        <div key={section.title} id={epId(section.title)}>
                            <h3 className="text-blue-300 text-lg font-semibold mb-1">
                                {section.title}
                            </h3>
                            <p className="text-gray-400 text-sm mb-4">
                                {section.description}
                            </p>
                            <div className="space-y-4">
                                {section.endpoints.map((ep) => (
                                    <EndpointCard
                                        key={ep.title}
                                        endpoint={ep}
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
