import { useEffect, useRef, useState } from "react";

const VideoPlayer = ({ id, slug }) => {
    let src = "/fetch-file/" + id;

    src += slug ? "/" + slug : "";
    const [autoplay] = useState(() => {
        const savedAutoplay = localStorage.getItem("videoAutoplay");
        return savedAutoplay !== null ? JSON.parse(savedAutoplay) : false;
    });
    const videoRef = useRef(null);

    useEffect(() => {
        if (videoRef.current) {
            videoRef.current.autoplay = autoplay;
        }
    }, [autoplay]);

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
        </div>
    );
};

export default VideoPlayer;
