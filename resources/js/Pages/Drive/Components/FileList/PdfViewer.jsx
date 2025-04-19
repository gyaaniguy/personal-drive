import {Document, Page, pdfjs} from 'react-pdf';
import {useState} from "react";
import "react-pdf/dist/esm/Page/AnnotationLayer.css";
import "react-pdf/dist/esm/Page/TextLayer.css";
import { ArrowLeft, ArrowRight } from 'lucide-react';
import Button from "../Generic/Button.jsx";

pdfjs.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.min.mjs',
    import.meta.url,
).toString();

const PdfViewer = ({id, slug}) => {
    const [numPages, setNumPages] = useState(0)
    const [pageNumber, setPageNumber] = useState(1)
    let src = '/fetch-file/' + id;
    src += slug ? '/' + slug : ''

    function onDocumentLoadSuccess({numPages}) {
        setNumPages(numPages);
    }

    return (
        <div className="mx-auto w-full ">
            <p className='flex items-center justify-between gap-x-2 mb-2'>
                <span>Page {pageNumber} of {numPages}</span>
                <div className='flex gap-x-2'>                
                    <Button
                    classes={`border border-green-800 text-green-200  btn-sm inline ${pageNumber <= 1 ? 'cursor-not-allowed hover:' : 'hover:bg-green-950 active:bg-gray-900'}`}
                    disabled={pageNumber <= 1}
                    onClick={() => setPageNumber(pageNumber - 1)}
                >
                    <ArrowLeft className="text-center text-green-500  hidden sm:inline  w-4 h-4"/> 
                    <span className="mx-1 text-sm">Prev Page</span>
                </Button>        
                <Button
                    classes={`border border-green-800 text-green-200 btn-sm inline ${pageNumber === numPages ? 'cursor-not-allowed hover:' : 'hover:bg-green-950 active:bg-gray-900'}`}
                    disabled={pageNumber === numPages}

                    onClick={() => setPageNumber(pageNumber + 1)}
                >
                    <ArrowRight className="text-center text-green-500  hidden sm:inline  w-4 h-4"/> 
                    <span className="mx-1 text-sm">Next Page</span>
                </Button></div>
            </p>
            <Document
                file={src} onLoadSuccess={onDocumentLoadSuccess}
                className=""
                onLoadError={(error) => console.error('Error loading PDF:', error)}
            >
                <Page pageNumber={pageNumber} width="1000"/>
            </Document>
        </div>
    );
};

export default PdfViewer;