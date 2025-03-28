'use client'

import {useState} from 'react'
import {router} from "@inertiajs/react";
import Button from "./Generic/Button.jsx"
import {RefreshCwIcon} from "lucide-react";


export default function RefreshButton() {

    const [isLoading, setIsLoading] = useState(false)

    async function handleClick(e) {
        e.preventDefault();
        setIsLoading(true)
        router.post('/resync', {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['files', 'flash'],
            onFinish: () => {
                setIsLoading(false);
            }
        });
    }

    return (
        <div className='relative  m-0 p-0'>
            <Button
                classes={`transition-colors duration-200 inline-flex ${isLoading ? 'bg-gray-700 text-green-100 cursor-not-allowed' : 'bg-blue-700 hover:bg-blue-600 active:bg-blue-700'} `}
                disabled={isLoading}
                onClick={handleClick}
                title="Use: In case of missing files, regenerate thumbnail OR after manual changes to storage directory"
            >

                {isLoading ? (<div
                    className="w-5 h-5 border-t-2 border-blue-300 border-solid rounded-full animate-spin"></div>) : (
                    <>
                        <RefreshCwIcon className="text-blue-400 inline mr-1 scale-90 "/> <span>Re Sync</span>
                    </>

                )}
            </Button>
        </div>)
}
