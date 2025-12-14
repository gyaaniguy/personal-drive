export default function Checkbox({ className = "", ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                "rounded text-indigo-600 shadow-sm border-gray-700 bg-gray-900 focus:ring-indigo-600 focus:ring-offset-gray-800 " +
                className
            }
        />
    );
}
