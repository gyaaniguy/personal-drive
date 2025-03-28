import {forwardRef, useEffect, useImperativeHandle, useRef} from 'react';

export default forwardRef(function TextInput(
    {type = 'text', className = '', isFocused = false, ...props},
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'rounded-md  shadow-sm border-gray-700 bg-gray-900 text-gray-300 focus:border-indigo-600 focus:ring-indigo-600 ' +
                className
            }
            ref={localRef}
        />
    );
});
