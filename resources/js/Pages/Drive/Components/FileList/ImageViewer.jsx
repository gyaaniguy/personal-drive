const ImageViewer = ({ id, slug }) => {
    let src = "/fetch-file/" + id;
    src += slug ? "/" + slug : "";
    return (
        <img
            className="max-h-screen  object-contain"
            src={src} // Dynamically load the file
            alt="Selected File"
        />
    );
};

export default ImageViewer;
