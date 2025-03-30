import React from 'react';
import Modal from './Modal.jsx';
import {useForm} from '@inertiajs/react';

const ReplaceAbort = ({isReplaceAbortModalOpen, setIsReplaceAbortModalOpen}) => {
    const {data, setData, post} = useForm({action: ''});

    const forceReloadImages = () => {
        console.log('forceReloadImages');
        document.querySelectorAll('img').forEach(img => {
            if (img.src.includes('/fetch-thumb/')) {
                img.src = img.src + `?t=${Date.now()}`;
            }
        });
    };

    const handleCloseModal = (status) => {
        setIsReplaceAbortModalOpen(status);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/abort-replace', {
            preserveState: true,
            preserveScroll: true,
            only: ['files', 'flash', 'errors'],
            onSuccess: (response) => {
                console.log('response', response);
                handleCloseModal(false);
                setTimeout(forceReloadImages, 500);
            },
        });
    };

    return (
        <Modal isOpen={isReplaceAbortModalOpen} onClose={handleCloseModal} title="Duplicates found" classes=""
               shouldCloseOnOverlayClick={false}>
            <div className="text-gray-400  text-sm mb-4">We found files/folders that already exist.<br/>
                How to handle these duplicates ?
            </div>
            <div className="space-y-2">
                <form onSubmit={handleSubmit} className="space-y-4 text-gray-300 max">
                    <div className="flex flex-col items-start space-y-4">
                        <label className="inline-flex items-center hover:text-blue-300 w-full py-1">
                            <input type="radio" name="action" value="abort" className="form-radio text-blue-500"
                                   onChange={(e) => setData('action', e.target.value)}/>
                            <span className="ml-2">Skip Duplicates</span>
                        </label>
                        <label className="inline-flex items-center hover:text-blue-300 w-full py-1">
                            <input type="radio" name="action" value="overwrite" className="form-radio text-blue-500"
                                   onChange={(e) => setData('action', e.target.value)}/>
                            <span className="ml-2">Overwrite Everything</span>
                        </label>
                    </div>
                    <button type="submit"
                            className="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Submit
                    </button>
                </form>
            </div>
        </Modal>
    );
};

export default ReplaceAbort;