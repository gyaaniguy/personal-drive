import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import GuestLayout from "@/Pages/Drive/Layouts/GuestLayout";
import { Head, useForm } from "@inertiajs/react";

export default function TowFactorCheck({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: "",
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route("login.two-factor-check"), {
            onFinish: () => {
                reset("password");
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Enter Two factor Authentication code" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="code" value="Enter OTP shown in authenticator app" className="text-center my-4"/>

                    <TextInput
                        id="code"
                        type="code"
                        name="code"
                        value={data.code}
                        className="mt-1 block w-full p-2  border"
                        autoComplete=""
                        isFocused={true}
                        onChange={(e) => setData("code", e.target.value)}
                    />

                    <InputError message={errors.code} className="mt-2" />
                </div>

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton className="ms-4" disabled={processing}>
                        Submit
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
