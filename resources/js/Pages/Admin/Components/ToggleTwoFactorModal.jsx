import {useEffect, useState} from "react";
import Modal from "../../Drive/Components/Modal.jsx";
import {router} from "@inertiajs/react";
import axios from "axios";
import { usePage } from "@inertiajs/react";

const ToggleTwoFactorModal = ({isTwoFaModalOpen, setIsTwoFaModalOpen, twoFactorStatus = false}) => {
    let { flash, errors } = usePage().props;

    const [qrSvg, setQrSvg] = useState("");
    const [twoFactorCode, setTwoFactorCode] = useState("");
    console.log('isTwoFaModalOpen ', isTwoFaModalOpen );

    useEffect(() => {
        const generateQr = async () => {
            if (!isTwoFaModalOpen || twoFactorStatus === '1') return;

            const response = await axios.post(
                route("admin-config.toggle-two-factor"),
                {twoFactorStatus: twoFactorStatus}
            );
            setQrSvg(response.data.message);
            console.log(response.data);

        };
        generateQr();
    }, [isTwoFaModalOpen, twoFactorStatus]);

    function handleCloseModal() {
        setQrSvg('');
        setIsTwoFaModalOpen(false)
    }

    const handleSubmit = async (e) => {
        e.preventDefault();
        const formData = {};
        formData["code"] = twoFactorCode;
        router.post(
            route(twoFactorStatus === '1' ? "admin-config.two-factor-code-disable" : "admin-config.two-factor-code-enable"),
            formData,
            {
                preserveState: true,
                preserveScroll: true,
                // only: ["flash"],
                onSuccess: (page) => {
                    setTwoFactorCode("");
                    console.log('page.props ', page.props);
                    console.log('page.props.status ', page.props.status);
                    if (page.props.flash.status){
                        console.log('page.props.status')
                        handleCloseModal();
                    }

                },
            }
        );
    };

    let title = (twoFactorStatus === '1' ? 'Disable' : 'Enable') + `Two factor authentication`;
    return (
        <Modal
            isOpen={isTwoFaModalOpen}
            onClose={handleCloseModal}
            title={title}
            classes="max-w-md"
        >
            {!qrSvg && twoFactorStatus === '0' &&
                <div className="space-y-4"> Loading Qr code ..</div>
            }
            { ( twoFactorStatus === '1' || (twoFactorStatus === '0' && qrSvg ) )  &&
                <div className="space-y-4">
                    {twoFactorStatus === '0' &&
                        <div>
                            Scan QR in Authenticator App like "Google Authenticator"
                            <div
                                dangerouslySetInnerHTML={{__html: qrSvg}}
                            />
                        </div>
                    }
                    <form
                        onSubmit={handleSubmit}
                        className="space-y-4 text-gray-300"
                    >
                        <div>
                            <label
                                htmlFor="code"
                                className="block text-sm font-medium"
                            >
                                Auth Code:
                            </label>
                            <input
                                type="text"
                                id="code"
                                value={twoFactorCode}
                                onChange={(e) => setTwoFactorCode(e.target.value)}
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
            }
        </Modal>
    );
};

export default ToggleTwoFactorModal;
