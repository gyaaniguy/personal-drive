import {useEffect, useState} from "react";
import Modal from "../../Drive/Components/Modal.jsx";
import {router} from "@inertiajs/react";
import axios from "axios";
import {usePage} from "@inertiajs/react";
import TextInput from "@/Components/TextInput.jsx";

const ToggleTwoFactorModal = ({isTwoFaModalOpen, setIsTwoFaModalOpen, twoFactorStatus = false}) => {
    let {flash, errors} = usePage().props;

    const [qrSvg, setQrSvg] = useState("");
    const [twoFactorCode, setTwoFactorCode] = useState("");
    console.log('isTwoFaModalOpen ', isTwoFaModalOpen);

    useEffect(() => {
        const generateQr = async () => {
            if (!isTwoFaModalOpen || twoFactorStatus) return;

            const response = await axios.post(
                route("admin-config.two-factor-qr"),
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
            route(twoFactorStatus ? "admin-config.two-factor-code-disable" : "admin-config.two-factor-code-enable"),
            formData,
            {
                preserveState: true,
                preserveScroll: true,
                // only: ["flash"],
                onSuccess: (page) => {
                    setTwoFactorCode("");
                    if (page.props.flash.status) {
                        handleCloseModal();
                    }

                },
            }
        );
    };

    let title = (twoFactorStatus ? 'Disable' : 'Enable') + ` Two factor authentication`;
    return (
        <Modal
            isOpen={isTwoFaModalOpen}
            onClose={handleCloseModal}
            title={title}
            classes="max-w-md"
        >
            {!qrSvg && !twoFactorStatus &&
                <div className="space-y-4"> Loading Qr code ..</div>
            }
            {(twoFactorStatus || (!twoFactorStatus && qrSvg)) &&
                <>
                    {!twoFactorStatus && <>
                        <div className="mb-4">
                            Scan below QR in any TOTP Authenticator application. Example: "Google Authenticator"
                        </div>
                        <div className="flex justify-center items-center"
                            dangerouslySetInnerHTML={{__html: qrSvg}}
                        />
                    </>
            }
            <form
                onSubmit={handleSubmit}
                className=" text-gray-300"
            >
                <div className="flex my-5 items-center justify-center gap-3">
                    <label
                        htmlFor="code"
                        className=" text-sm font-medium"
                    >
                        Auth Code:
                    </label>

                    <TextInput
                        id="code"
                        type="code"
                        name="code"
                        value={twoFactorCode}
                        className="bg-gray-700/90 border border-gray-300 rounded-md p-1 md:p-2 sm:pr-8 focus:outline-none focus:ring-2 focus:ring-blue-500  w-28 sm:w-44 md:w-52"
                        isFocused={true}
                        onChange={(e) => setTwoFactorCode(e.target.value)}
                    />
                </div>
                <button
                    type="submit"
                    className="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                >
                    Submit
                </button>
            </form>
        </>
}
</Modal>
)
    ;
};

export default ToggleTwoFactorModal;
