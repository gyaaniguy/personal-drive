import React, {useState} from 'react';
import Modal from './Modal.jsx'
import {router} from "@inertiajs/react";


const CreateItemModal = ({isModalOpen, setIsModalOpen, path, isFile}) => {
    const [itemName, setItemName] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        const formData = {};
        formData['path'] = path;
        formData['itemName'] = itemName;
        formData['isFile'] = isFile.current;
        setIsModalOpen(false);
        router.post('/create-item', formData, {
            only: ['files', 'flash'],
        });
    }

    return (
        <Modal isOpen={isModalOpen} onClose={setIsModalOpen} title={`Create ${isFile.current ? "File" : "Folder"}`} 
        classes="max-w-md ">
            <div className="space-y-4">
                <form onSubmit={handleSubmit} className="space-y-4 text-gray-300">
                    <div>
                        <label htmlFor="itemName" className="block text-sm font-medium ">
                            {isFile.current && "File "}
                            {!isFile.current && "Folder "}
                             Name
                        </label>
                        <input
                            type="text"
                            id="itemName"
                            value={itemName}
                            onChange={(e) => setItemName(e.target.value)}
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

export default CreateItemModal;

