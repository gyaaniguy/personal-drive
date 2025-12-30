import { useEffect, useRef, useState } from "react";
import { useLocalStorageBool } from "@/Pages/Drive/Hooks/useLocalStorageBool.jsx";

const AudioPlayer = ({ id, slug }) => {
    let src = "/fetch-file/" + id;
    src += slug ? "/" + slug : "";

    const audioRef = useRef(null);

    const [audioAutoplay] = useLocalStorageBool("audioAutoplay");
    const [audioSavePos] = useLocalStorageBool("audioSavePosition");

    const POSITION_KEY = `audio-position-${id}`;

    useEffect(() => {
        const audio = audioRef.current;
        if (!audio || !audioSavePos) return;
        const savedTime = localStorage.getItem(POSITION_KEY);
        if (savedTime) {
            audio.currentTime = Number(savedTime);
        }

        const saveTime = () => {
            localStorage.setItem(POSITION_KEY, audio.currentTime);
        };

        audio.addEventListener("timeupdate", saveTime);

        return () => {
            audio.removeEventListener("timeupdate", saveTime);
        };
    }, [id]);

    const rewind = (seconds) => {
        const audio = audioRef.current;
        if (!audio) return;
        audio.currentTime = Math.max(0, audio.currentTime - seconds);
    };

    const fastf = (seconds) => {
        const audio = audioRef.current;
        if (!audio) return;
        audio.currentTime = Math.max(0, audio.currentTime + seconds);
    };

    return (
        <div className="flex justify-center flex-col gap-y-2">
            <audio
                ref={audioRef}
                key={id}
                controls
                autoPlay={audioAutoplay}
                className="w-[90vw] md:w-[50vw] "
            >
                <source src={src} type="audio/mpeg" />
                Your browser does not support the audio tag.
            </audio>

            <div className="flex justify-between text-gray-400 text-sm">
                <div className="flex gap-x-2 ">
                    <button
                        onClick={() => rewind(60)}
                        className="group hover:bg-blue-950 active:bg-blue-900 p-1 rounded cursor-pointer "
                    >
                        <span className="  ">
                            <span className="group-hover:hidden">◁◁</span>
                            <span className="hidden group-hover:inline">
                                ◀◀
                            </span>
                        </span>{" "}
                        <span className="text-xs text-blue-200">1m</span>
                    </button>
                    <button
                        onClick={() => rewind(10)}
                        className="group hover:bg-blue-950 active:bg-blue-900 p-1 rounded cursor-pointer "
                    >
                        <span className="  ">
                            <span className="group-hover:hidden">◁◁</span>
                            <span className="hidden group-hover:inline">
                                ◀◀
                            </span>
                        </span>{" "}
                        <span className="text-xs text-blue-200">10s</span>
                    </button>
                </div>
                <div className="flex gap-x-2">
                    <button
                        onClick={() => fastf(10)}
                        className="group hover:bg-blue-950 active:bg-blue-900 p-1 rounded cursor-pointer "
                    >
                        <span className="  ">
                            <span className="group-hover:hidden">▷▷</span>
                            <span className="hidden group-hover:inline">
                                ▶▶
                            </span>
                        </span>{" "}
                        <span className="text-xs text-blue-200">10s</span>
                    </button>
                    <button
                        onClick={() => fastf(60)}
                        className="group hover:bg-blue-950 active:bg-blue-900 p-1 rounded cursor-pointer "
                    >
                        <span className="  ">
                            <span className="group-hover:hidden">▷▷</span>
                            <span className="hidden group-hover:inline">
                                ▶▶
                            </span>
                        </span>{" "}
                        <span className="text-xs text-blue-200">1m</span>
                    </button>
                </div>
            </div>
        </div>
    );
};

export default AudioPlayer;
