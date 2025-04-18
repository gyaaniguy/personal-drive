import React, { useEffect, useRef, useState, useCallback } from 'react';
import axios from 'axios';
import { Marked } from "marked";
import { markedHighlight } from "marked-highlight";
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css'; // Or any other theme you prefer


const TxtViewer = ({ previewFile, slug, isEditingRef, isFocusedRef, isInEditMode, setIsInEditMode, isAdmin}) => {
    const [content, setContent] = useState('');
    const [editedContent, setEditedContent] = useState('');
    const [isSaving, setIsSaving] = useState(false);
    const [savedMessage, setSavedMessage] = useState('');
    const textareaRef = useRef(null);
    const editedContentRef = useRef('');

    const marked = new Marked(
        markedHighlight({
            emptyLangClass: 'hljs',
            langPrefix: 'hljs language-',
            highlight(code, lang, info) {
                const language = hljs.getLanguage(lang) ? lang : 'plaintext';
                return hljs.highlight(code, { language }).value;
            }
        })
    );
    

    const handleFocus = () => {
        isFocusedRef.current = true;
    };

    const handleBlur = () => {
        isFocusedRef.current = false;
    };

    const fetchTextFile = async (src) => {
        try {
            const response = await axios.get(src);
            setContent(response.data || '');
            setEditedContent(response.data || '');
        } catch (err) {
            console.error('Error fetching file:', err);
        }
    };

    const saveChanges = async (contentToSave = editedContent) => {
        setIsSaving(true);
        try {
            const response = await axios.post(`/save-file`, { id: previewFile.id, content: contentToSave });
            setContent(contentToSave);
            setTimeout(() => setSavedMessage(''), 3000);
            isEditingRef.current = false;
            isFocusedRef.current = false;
            if (textareaRef.current) {
                textareaRef.current.blur();
            }
            if ( response.data?.message.includes('success') ) {
                setSavedMessage('Changes saved successfully!');
                let src = '/fetch-file/' + previewFile.id + `?t=${Date.now()}`;
                fetchTextFile(src);
            } else {
                setSavedMessage('Error: ' + response.data?.message);
            }
        } catch (err) {
            console.error('Error saving file:', err);
        } finally {
            setIsSaving(false);
        }
    };

    const startEditing = () => {
        if (!isAdmin){
            return;
        }
        setIsInEditMode(true);
    };

    const discardChanges = () => {
        setEditedContent(content);
        isEditingRef.current = false;
        setIsInEditMode(false);
        setSavedMessage('');
    };

    const fetchSrcFile = (src) => {
        src += slug ? '/' + slug : '';
        fetchTextFile(src);
    }
    
    useEffect(() => {
        let src = '/fetch-file/' + previewFile.id + `?t=${Date.now()}`;
        fetchSrcFile(src);
    }, [previewFile, slug]);

    useEffect(() => {
        editedContentRef.current = editedContent;
    }, [ editedContent]);

    const handleKeyDown = useCallback((e) => {
        if (e.ctrlKey && e.key === 'Enter' && isInEditMode) {
            saveChanges(editedContentRef.current);
        }
    }, [isInEditMode, previewFile]);

    useEffect(() => {
        window.addEventListener('keydown', handleKeyDown);
        return () => {
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [handleKeyDown]);

    return (
        <div className="relative overflow-auto">
            {isInEditMode ? (
                <textarea
                    ref={textareaRef}
                    className="w-[90vw] md:w-[70vw] h-[70vh] resize-none bg-gray-900 text-300 overflow-auto"
                    value={editedContent}
                    onChange={(e) => {
                        setEditedContent(e.target.value);
                        isEditingRef.current = true;
                    }}
                    onFocus={handleFocus}
                    onBlur={handleBlur}
                />
            ) : previewFile.filename.endsWith('.md') ? (
                    <div
                        className="prose prose-invert w-[90vw] md:w-[70vw] cursor-pointer "
                        dangerouslySetInnerHTML={{ __html: marked.parse(content || 'Click to edit..') }}
                        onClick={startEditing}
                    />
                ) : (
                    <pre className="w-[70vw] cursor-pointer" onClick={startEditing}>
                        {content || 'Click to edit...'}
                    </pre>
                )
            }
            {isInEditMode && (
                <div className="grid grid-cols-3 mt-2 w-full items-center text-sm md:text-base">
                    <div className="col-span-1">
                        <button
                            className="px-2 py-1 bg-gray-500 rounded text-gray-950"
                            onClick={discardChanges}
                        >
                            Discard
                        </button>
                    </div>
                    <div className="text-gray-200 p-2 col-span-1 justify-self-center">
                    {previewFile.filename}  <span className="ml-2 text-gray-400 text-sm">{savedMessage || "in edit mode"}</span>
                    </div>
                    <button
                        className="px-2 py-1 bg-blue-500 rounded disabled:opacity-50 col-span-1 justify-self-end text-gray-900"
                        onClick={() => saveChanges()}
                        disabled={isSaving}
                    >
                        {isSaving ? 'Saving...' : <>Save <span className="text-sm text-gray-700 ml-2 hidden md:inline">Ctrl + <span className="text-sm">↵</span></span></>}
                    </button>
                </div>
            )}
        </div>
    );
};

export default TxtViewer;
