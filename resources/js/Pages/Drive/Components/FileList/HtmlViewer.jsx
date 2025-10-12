const HtmlViewer = ({ id, slug }) => {
    let src = "/fetch-file/" + id;
    src += slug ? "/" + slug : "";
    console.log('html', src);
    return (
        <iframe
            className="h-[90vh] w-[80vw] object-contain"
            src={src}
            title="HTML Content"
            frameBorder="0"
        />
    );
};

export default HtmlViewer;
