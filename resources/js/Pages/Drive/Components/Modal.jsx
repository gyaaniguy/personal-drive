const Modal = ({
    isOpen,
    onClose,
    title,
    children,
    classes,
    shouldCloseOnOverlayClick = true,
}) => {
    return (
        isOpen && (
            <div
                className={`fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-40 text-gray-300`}
                onClick={() => shouldCloseOnOverlayClick && onClose(false)}
            >
                <div
                    className={`bg-gray-900 border-gray-700 border-4 rounded-lg shadow-xl  mx-4 ${classes}`}
                    onClick={(e) => e.stopPropagation()}
                >
                    {title && (
                        <div className="flex justify-between items-center border-b-gray-700 border-b-4 p-4">
                            <h2 className="text-xl font-semibold">{title}</h2>
                            <button
                                className="hover:text-gray-700 focus:outline-none"
                                onClick={() => onClose(false)}
                            >
                                <svg
                                    className="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    )}
                    <div className="p-4 max-h-[90vh] overflow-y-auto">
                        {children}
                    </div>
                </div>
            </div>
        )
    );
};

export default Modal;
