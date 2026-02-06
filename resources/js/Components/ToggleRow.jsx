export default function ToggleRow  ({ label, enabled, onToggle }) {
    return <div className="flex items-center justify-between w-full max-w-sm py-3">
        <span className="text-gray-200 text-lg font-semibold">{label}</span>
        <button
            onClick={onToggle}
            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${enabled ? 'bg-green-600' : 'bg-red-600'}`}
        >
            <span
                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${enabled ? 'translate-x-6' : 'translate-x-1'}`}/>
        </button>
    </div>
}
