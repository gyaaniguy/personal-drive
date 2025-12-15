import { useEffect, useRef } from "react";
import { useLocalStorageBool } from "@/Pages/Drive/Hooks/useLocalStorageBool.jsx";

const VideoPlayer = ({ id, slug }) => {
    let src = "/fetch-file/" + id;

    src += slug ? "/" + slug : "";

    const [videoAutoplay] = useLocalStorageBool("videoAutoplay");

    const videoRef = useRef(null);

    useEffect(() => {
        if (videoRef.current) {
            videoRef.current.autoplay = videoAutoplay;
        }
    }, [videoAutoplay]);

    return (
        <div className="flex justify-center flex-col gap-y-2 ">
            <video
                ref={videoRef}
                key={id}
                controls
                autoPlay={videoAutoplay}
                className="max-w-2xl rounded-lg shadow-lg max-h-[90vh]"
            >
                <source src={src} type="video/mp4" />
                Your browser does not support the video tag.
            </video>
        </div>
    );
};

export default VideoPlayer;
