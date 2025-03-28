import React from 'react';

const Button = ({onClick, classes, children, ...props}) => {
    return (
        <button className={`p-1 md:p-2 text-xs md:text-base rounded-md flex items-center ${classes}`}
                onClick={onClick} {...props}>
            {children}
        </button>
    );
};

export default Button;