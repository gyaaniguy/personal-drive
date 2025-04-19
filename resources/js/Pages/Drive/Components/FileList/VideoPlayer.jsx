import { useEffect, useRef, useState } from "react";

const VideoPlayer = ({ id, slug }) => {
    let src = "/fetch-file/" + id;

    src += slug ? "/" + slug : "";
    const [autoplay, setAutoplay] = useState(() => {
        const savedAutoplay = localStorage.getItem("videoAutoplay");
        return savedAutoplay !== null ? JSON.parse(savedAutoplay) : false;
    });
    const videoRef = useRef(null);

    useEffect(() => {
        if (videoRef.current) {
            videoRef.current.autoplay = autoplay;
        }
    }, [autoplay]);

    const handleAutoplayToggle = () => {
        localStorage.setItem("videoAutoplay", JSON.stringify(!autoplay));
        setAutoplay(!autoplay);
    };

    return (
        <div className="flex justify-center flex-col gap-y-2 ">
            <video
                ref={videoRef}
                key={id}
                controls
                autoPlay={autoplay}
                className="max-w-2xl rounded-lg shadow-lg max-h-[90vh]"
            >
                <source src={src} type="video/mp4" />
                Your browser does not support the video tag.
            </video>
            <div className="flex items-center space-x-2">
                <input
                    type="checkbox"
                    id="autoplay"
                    checked={autoplay}
                    onChange={handleAutoplayToggle}
                    className="w-4 h-4 text-blue-600 rounded focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600"
                />
                <label
                    htmlFor="autoplay"
                    className="text-sm font-medium text-gray-300"
                >
                    Autoplay
                </label>
            </div>
        </div>
    );
};

export default VideoPlayer;
