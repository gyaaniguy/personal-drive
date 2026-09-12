const HtmlViewer = ({ id, slug }) => {
    let src = "/fetch-file/" + id;
    src += slug ? "/" + slug : "";
    return (
        <iframe
            className="h-[90vh] w-[80vw] object-contain"
            src={src}
            title="HTML Content"
            frameBorder="0"
            sandbox=""
        />
    );
};

export default HtmlViewer;
