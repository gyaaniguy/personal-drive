export default function PrimaryButton({
    className = "",
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-transparent  px-4 py-2 text-xs font-semibold uppercase tracking-widest transition duration-150 ease-in-out  focus:outline-none focus:ring-2  focus:ring-offset-2 bg-blue-700 text-gray-200 font-bold hover:bg-blue-600  focus:ring-offset-gray-800 active:bg-blue-800 ${
                    disabled && "opacity-25"
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
