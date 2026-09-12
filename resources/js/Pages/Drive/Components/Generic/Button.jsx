const Button = ({ onClick, classes, size = "default", children, ...props }) => {
    const sizeClasses = size === "selected" ? "text-xs xl:text-sm" : "text-sm";

    return (
        <button
            className={`min-h-7 min-w-7 rounded-md px-1 md:px-2 py-1 inline-flex items-center justify-center gap-1 ${sizeClasses} ${classes}`}
            onClick={onClick}
            {...props}
        >
            {children}
        </button>
    );
};

export default Button;
