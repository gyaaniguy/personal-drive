import React, {useEffect, useState} from 'react';
import Modal from '../Modal.jsx'
import {router} from "@inertiajs/react";

const RenameModal = ({
                         isRenameModalOpen, setIsRenameModalOpen,
                         setFileToRename, fileToRename,
                         path,
                     }) => {

    const [formData, setFormData] = useState(() => ({
        id: fileToRename?.id || "",
        filename: fileToRename?.filename || ""
    }));

    useEffect(() => {
        if (fileToRename) {
            setFormData({id: fileToRename.id, filename: fileToRename.filename});
        }
    }, [fileToRename]);
    const handleChange = (e) => {
        const {id, value} = e.target;
        console.log(value, id);
        setFormData(prevState => ({
            ...prevState,
            [id]: value
        }));
    };

    function handleCloseRenameModal(status) {
        setIsRenameModalOpen(status)
        setFileToRename?.(new Set());
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        router.post('/rename-file', {
            ...formData,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['files','flash', 'errors'],
            onSuccess: (response) => {
                console.log(response);
                handleCloseRenameModal(false);
            },
            onFinish: () => {

            }
        });
    }

    return (
        <Modal isOpen={isRenameModalOpen} onClose={handleCloseRenameModal} title="Rename file" classes=" ">
            <div className="space-y-4">
                <form onSubmit={handleSubmit} className="space-y-4 text-gray-300 max:w-[90vw] w-[50vw]">
                    <div>
                        <input
                            type="text"
                            id="filename"
                            value={formData.filename ?? ""}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800"
                            required
                        />
                    </div>
                    <button
                        type="submit"
                        className="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                    >
                        Submit
                    </button>
                </form>
            </div>
        </Modal>
    );
};

export default RenameModal;

