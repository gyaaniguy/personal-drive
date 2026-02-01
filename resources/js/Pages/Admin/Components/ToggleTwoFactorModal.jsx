import {useEffect, useState} from "react";
import Modal from "../../Drive/Components/Modal.jsx";
import {router} from "@inertiajs/react";
import axios from "axios";

const ToggleTwoFaModal = ({isTwoFaModalOpen, setIsTwoFaModalOpen, twoFactorStatus = false}) => {
    const [qrLoaded, setQrLoaded] = useState(false);
    useEffect( () => {
        const toggleTwoFactor = async () => {
            if (!isTwoFaModalOpen) return;

            const response = await axios.post(
                    route("admin-config.toggle-two-factor"),
                    { twoFactorStatus : !!twoFactorStatus }
                );

            console.log(response.data);

        };
        toggleTwoFactor();
    }, [isTwoFaModalOpen, twoFactorStatus]);


    return (
        <Modal
            isOpen={isTwoFaModalOpen}
            onClose={setIsTwoFaModalOpen}
            title={`Enable/Disable Two factor authentication`}
            classes="max-w-md "
        >
            {!qrLoaded &&
                <div className="space-y-4"> Loading Qr code ..</div>
            }
            {qrLoaded &&
                <div className="space-y-4">
                    <form
                        className="space-y-4 text-gray-300"
                    >
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

export default ToggleTwoFaModal;
