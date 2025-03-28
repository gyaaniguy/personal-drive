// src/App.js
import React from 'react';
import {FiArrowUp, FiCheckSquare, FiGithub, FiLayout, FiLock, FiServer, FiStar, FiVideo, FiZap} from 'react-icons/fi';
import {DiLaravel, DiReact} from 'react-icons/di';

const App = () => {
    return (
        <div className="font-sans bg-gray-900 text-white min-h-screen">
            {/* Navbar */}
            <nav className="bg-gray-800 shadow-lg">
                <div className="max-w-6xl mx-auto px-4">
                    <div className="flex justify-between items-center py-4">
                        <div className="flex items-center space-x-2">
                            <FiServer className="text-green-400 text-xl"/>
                            <span className="text-lg font-semibold text-green-400">PersonalDrive</span>
                        </div>
                        <a
                            href="https://github.com/yourusername/personaldrive"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center bg-green-500 text-white font-bold py-2 px-4 rounded-full hover:bg-green-600 transition duration-300"
                        >
                            <FiStar className="mr-2"/>
                            Star on GitHub
                        </a>
                    </div>
                </div>
            </nav>

            {/* Hero Section */}
            <div className="bg-gray-900 py-20 relative overflow-hidden">
                <div className="max-w-4xl mx-auto text-center px-4 relative z-10">
                    <div className="mb-8 animate-bounce">
                        <svg viewBox="0 0 200 200" className="w-32 h-32 mx-auto">
                            <circle cx="100" cy="100" r="80" fill="none" stroke="#4ADE80" strokeWidth="8"/>
                            <path
                                d="M50,100 L100,150 L150,50"
                                stroke="#4ADE80"
                                strokeWidth="8"
                                fill="none"
                                strokeLinecap="round"
                            />
                        </svg>
                    </div>
                    <h1 className="text-5xl font-bold text-green-400 mb-6">Your Personal Cloud Storage</h1>
                    <p className="text-xl text-gray-400 mb-8 max-w-2xl mx-auto">
                        Open-source, self-hosted solution with a modern interface and powerful features
                    </p>
                    <div className="flex justify-center space-x-4">
                        <a
                            href="https://github.com/yourusername/personaldrive"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="bg-green-500 text-white font-bold py-3 px-6 rounded-full hover:bg-green-600 transition duration-300 flex items-center"
                        >
                            <DiReact className="mr-2 text-xl"/>
                            Get Started
                        </a>
                    </div>
                </div>

                {/* Background Waves */}
                <svg
                    className="absolute bottom-0 left-0 right-0 text-gray-800"
                    viewBox="0 0 1440 320"
                >
                    <path fill="currentColor" fillOpacity="1"
                          d="M0,128L48,138.7C96,149,192,171,288,160C384,149,480,107,576,106.7C672,107,768,149,864,154.7C960,160,1056,128,1152,122.7C1248,117,1344,139,1392,149.3L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                </svg>
            </div>

            {/* Features Section */}
            <div className="py-20 bg-gray-800">
                <div className="max-w-6xl mx-auto px-4">
                    <h2 className="text-4xl font-bold text-center text-green-400 mb-12">Features</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                        {/* Tech Stack Diagram */}
                        <div className="bg-gray-700 p-6 rounded-lg col-span-1 lg:col-span-2">
                            <div className="flex items-center justify-center mb-4">
                                <div className="relative w-32 h-32">
                                    <DiLaravel className="text-red-500 text-6xl absolute left-0"/>
                                    <DiReact className="text-blue-400 text-6xl absolute right-0"/>
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <div
                                            className="w-16 h-16 bg-green-400 rounded-full flex items-center justify-center">
                                            <FiZap className="text-white text-2xl"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h3 className="text-2xl font-bold text-green-400 mb-4 text-center">Modern Tech Stack</h3>
                            <p className="text-gray-300 text-center">
                                Powered by Laravel's robust backend and React's lightning-fast frontend
                            </p>
                        </div>

                        {/* Feature Cards */}
                        {[
                            {
                                icon: <FiCheckSquare/>,
                                title: "Smart Selection",
                                desc: "Select multiple files with checkboxes or click+drag"
                            },
                            {
                                icon: <FiVideo/>,
                                title: "Media Previews",
                                desc: "Autoplay videos & preview files without downloading"
                            },
                            {
                                icon: <FiArrowUp/>,
                                title: "Instant Sorting",
                                desc: "Drag-and-drop organization & column-based sorting"
                            },
                            {
                                icon: <FiLayout/>,
                                title: "Custom Views",
                                desc: "Switch between list, grid, or gallery layouts"
                            },
                            {icon: <FiLock/>, title: "Secure", desc: "End-to-end encryption and self-hosted storage"},
                        ].map((feature, idx) => (
                            <div key={idx}
                                 className="bg-gray-700 p-6 rounded-lg hover:transform hover:scale-105 transition-all duration-300">
                                <div className="text-green-400 text-4xl mb-4 flex justify-center">
                                    {feature.icon}
                                </div>
                                <h3 className="text-2xl font-bold text-center text-green-400 mb-2">
                                    {feature.title}
                                </h3>
                                <p className="text-gray-300 text-center">{feature.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Footer */}
            <footer className="bg-gray-900 py-10">
                <div className="max-w-6xl mx-auto px-4 text-center">
                    <div className="flex justify-center space-x-6 mb-4">
                        <a
                            href="https://github.com/yourusername/personaldrive"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-green-400 hover:text-green-500 text-2xl"
                        >
                            <FiGithub/>
                        </a>
                    </div>
                    <p className="text-gray-400">
                        Made with ❤️ by the open-source community
                    </p>
                    <p className="text-gray-400 text-sm mt-2">
                        &copy; {new Date().getFullYear()} PersonalDrive - MIT License
                    </p>
                </div>
            </footer>
        </div>
    );
};

export default App;