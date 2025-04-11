import React, {useState} from 'react';
import Modal from '../Modal.jsx'
import {Link, router} from "@inertiajs/react";
import {CopyIcon} from "lucide-react";


const ShareModal = ({
                        isShareModalOpen,
                        setIsShareModalOpen,
                        setSelectedFiles,
                        selectedFiles,
                        setSelectAllToggle,
                    }) => {

    let formDefaultData = {password: '', expiry: 7, slug: ''};
    const [formData, setFormData] = useState({...formDefaultData});
    const [copyToClipboard, setCopyToClipboard] = useState('Copy to clipboard');
    const [sharedLink, setSharedLink] = useState('');

    const handleChange = (e) => {
        const {id, value} = e.target;
        setFormData(prevState => ({
            ...prevState,
            [id]: value
        }));
    };

    function handleCloseShareModal(status) {
        setIsShareModalOpen(status)
        setSelectedFiles?.(new Set());
        setSelectAllToggle?.(false);
        setFormData(formDefaultData);
        setSharedLink('');
    }

    const handleCopy = (e) => {
        e.preventDefault();
        navigator.clipboard?.writeText(sharedLink).then(() => {
            setCopyToClipboard('Copied!');
        });
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        // setIsShareModalOpen(false);
        // formData['folderName'] = folderName;
        router.post('/share-files', {
            ...formData,
            fileList: Array.from(selectedFiles)
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['flash', 'errors'],
            onSuccess: (response) => {
                if (response.props?.flash?.shared_link) {
                    setSharedLink(response.props.flash.shared_link);
                }
                // show link from response and its associated controls like copy. also button to see all shares
            },
            onFinish: () => {

            }
        });
    }

    return (
        <Modal isOpen={isShareModalOpen} onClose={handleCloseShareModal} title={`Share ${selectedFiles.size} files`}
               classes="max-w-md ">
            <div className="space-y-4">
                <div className="text-sm flex flex-col text-gray-300/80">
                    <div className="mb-5">
                        <p className="text-gray-300 text-lg mt-3">Generate a link to share selected files. Recommended
                            to share only with trusted people.</p>
                    </div>
                    <ul className="mb-5">
                        <li><span className=" font-semibold text-gray-300/80 text-base">Password</span>: Highly
                            recommended
                        </li>
                        <li><span className=" font-semibold text-gray-300/80 text-base">Expiry</span>: Days after which
                            link will be invalid
                        </li>
                        <li><span className=" font-semibold text-gray-300/80 text-base">Custom Url </span>: If this
                            is empty a random path will be generated
                        </li>
                    </ul>

                    <p className="text-gray-400/90">All fields are optional. </p>

                </div>
                <form onSubmit={handleSubmit} className="space-y-3 text-gray-300">
                    <div>
                        <label htmlFor="password" className="block text-sm font-medium ">
                            Password
                        </label>
                        <input
                            type="password"
                            id="password"
                            value={formData.password}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800"
                        />
                    </div>
                    <div>
                        <label htmlFor="expiry" className="block text-sm font-medium ">
                            Expire in days
                        </label>
                        <input
                            type="text"
                            id="expiry"
                            value={formData.expiry}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800"
                        />
                    </div>
                    <div>
                        <label htmlFor="slug" className="block text-sm font-medium ">
                            Custom URL slug ( Numbers, letters dash and underscore )
                        </label>
                        <input
                            type="text"
                            id="slug"
                            value={formData.slug}
                            onChange={handleChange}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800"
                        />
                    </div>

                    {!sharedLink && <button
                        disabled={selectedFiles.size === 0}
                        type="submit"
                        className={`w-full bg-blue-500  text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline ${selectedFiles.size === 0 ? 'bg-gray-500' : 'hover:bg-blue-600'}`}
                    >
                        Get Sharable Link
                    </button>}
                    {sharedLink &&
                        <div>
                            <div className="mt-5 flex justify-center font-semibold">
                                Share Link Generated !
                            </div>
                            <div className="flex">
                                <input
                                    type="text"
                                    value={sharedLink}
                                    readOnly
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 bg-gray-800 text-gray-300"
                                />
                                <button onClick={handleCopy}
                                        className={`p-2 mx-1 rounded-md bg-gray-600 hover:bg-gray-500  relative group active:bg-gray-600`}
                                ><CopyIcon/>
                                    <span
                                        className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-xs text-white bg-black rounded opacity-0 group-hover:opacity-100">
                                        {copyToClipboard}
                                    </span>
                                </button>

                                <Link href='/shares-all'
                                      className={`text-sm text-center px-2 py-1 rounded-md  bg-blue-700 hover:bg-blue-600 active:bg-blue-700`}
                                > All Shares
                                </Link>

                            </div>
                        </div>
                    }
                </form>
            </div>
        </Modal>
    );
};

export default ShareModal;

