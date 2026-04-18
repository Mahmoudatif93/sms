// File: public/js/livechat-widget.js

(function(window, document) {
    // Configuration
    const CHAT_API_URL = 'https://api.dreams.sa/v1.0/livechat';
    // const CHAT_API_URL = 'https://dreams-api.test/v1.0/livechat';

    // Widget state
    let widgetId = null;
    let isActiveTab = true;
    let listChatHistory = null;
    let sessionId = null;
    let contactId = null;
    let initialized = false;
    let pusherConnection = null;
    let sesstionContinuation = false;
    let state = {};
    let elements = {};

    // File selection state (supports multiple files)
    let selectedFiles = [];
    let isSubmittingFiles = false;

    // Reply state
    let replyingToMessage = null;

    // Create and inject the widget CSS
    function injectStyles() {
        const css = `
            *, :after, :before {
                border: 0 solid #e5e7eb;
                box-sizing: border-box;
            }
            blockquote, dd, dl, figure, h1, h2, h3, h4, h5, h6, hr, p, pre {
                margin: 0;
            }
            .livechat-container {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 999999;
                font-family: Arial, sans-serif;
            }

            .livechat-container[data-position="left"] {
                left: 20px;
                right: auto;
            }

            .livechat-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background-color: var(--livechat-primary-color, #4CAF50);
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                position: relative;
            }

            .livechat-window {
                position: fixed;
                bottom: 90px;
                right: 20px;
                width: 360px;
                height: 500px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 5px 40px rgba(0, 0, 0, 0.2);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                display: none;
            }
            .livechat-window[data-position="left"]{
                left: 20px;
                right: auto;
            }
            /* Add more CSS for chat window components */
            .livechat-header {
                background-color: var(--livechat-primary-color, #4CAF50);
                color: white;
                padding: 12px 15px;
                display: flex;
                align-items: center;
                gap: 12px;
                position: relative;
                min-height: 60px;
                box-sizing: border-box;
                border-bottom: 1px solid transparent;
            }
            .livechat-header.light-theme {
                border-bottom-color: #ddd;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }
            .livechat-header.light-theme .livechatButton {
                background-color: rgba(0, 0, 0, 0.08);
            }
            .livechat-header.light-theme .livechatButton:hover {
                background-color: rgba(0, 0, 0, 0.15);
            }
            /* Light theme avatar styles */
            .light-theme-chat .avatar-placeholder,
            .light-theme-chat .groupAvaterMessage {
                background-color: #f0f0f0;
                color: #555;
            }
            .livechat-header-info {
                display: flex;
                align-items: center;
                gap: 12px;
                flex: 1;
                min-width: 0;
            }
            .livechat-logo {
                width: 42px;
                height: 42px;
                border-radius: 50%;
                object-fit: contain;
                background-color: #fff;
                padding: 3px;
                flex-shrink: 0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .livechat-header-text {
                flex: 1;
                min-width: 0;
            }
            .livechat-header-text h3 {
                font-size: 16px;
                font-weight: 600;
                margin: 0;
                padding: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .livechat-header-text p {
                font-size: 13px;
                margin: 4px 0 0 0;
                opacity: 0.9;
            }
            .livechat-header-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }
            .livechatButton {
                background-color: rgba(255, 255, 255, 0.15);
                border: none;
                border-radius: 50%;
                color: inherit;
                cursor: pointer;
                font-size: 18px;
                font-weight: 700;
                height: 28px;
                width: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background-color 0.2s ease;
            }
            .livechatButton:hover {
                background-color: rgba(255, 255, 255, 0.25);
            }
            .livechat-body{
                background: #2f80ed1f;
                box-sizing: border-box;
                padding: 0px 0px 0px;
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                position: relative;
                overflow: hidden;
            }
            .livechat-content{
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                padding: .5rem 1rem 1rem 1rem;
                background-color: rgb(255 255 255);
                border-radius: .75rem;
                width: 100% !important;
                box-sizing: border-box;
                overflow-y: auto;
                max-height: 100%;
            }
            .livechat-pre-chat-form-container{
                width: 100%;
                max-height: 100%;
                overflow-y: auto;
                padding: 10px;
                box-sizing: border-box;
            }
            .livechat-form-field{
               display: flex;
               flex-direction: column;
               margin-bottom: 16px;
            }
            .livechat-form-field.rtl{
               direction: rtl;
            }
            .livechat-form-field label{
                font-weight: 500;
                font-size: .875rem;
                line-height: 1.25rem;
                margin-bottom: .25rem;
            }
            .livechat-form-field input:not(.form-radio):not(.form-checkbox),
            .livechat-form-field textarea{
                border-radius: .5rem;
                border-width: 1px;
                width: 100%;
                border-color: #d0d5dd;
                background-color: #f9fafb;
                font-size: .875rem;
                font-weight: 600;
                line-height: 1.25rem;
                padding: .5rem 1rem;
                color: rgb(26 28 33);
                outline: 2px solid transparent !important;
                outline-offset: 2px !important;
            }
            .livechat-form-field textarea{
                font-family: inherit;
                resize: vertical;
                min-height: 80px;
            }
            .groupAvater{
               display: flex;
               justify-content: center;
                align-items: center;
            }
            .livechat-submit-btn{
                border-radius: .5rem;
                margin-top: 1rem;
                width: 100%;
                color: rgb(255 255 255);
                font-weight: 600;
                padding: .5rem;
                font-size: 16px;
                background-color: rgb(147 51 234);
                cursor: pointer;
            }
            .livechat-submit-btn.rtl{
                direction: rtl;
            }
            .livechat-info-message{
                background-color: #f0f9ff;
                border: 1px solid #bae6fd;
                border-radius: .5rem;
                padding: .75rem 1rem;
                font-size: .875rem;
                line-height: 1.5;
                color: #0369a1;
            }
            .livechat-info-message.rtl{
                direction: rtl;
                text-align: right;
            }
            select {
                width: 100%;
                padding: 8px;
                font-size: 16px;
                border: 1px solid #ccc;
                border-radius: 4px;
                background-color: white;
                appearance: none; /* Removes default styles */
            }
            select option {
                font-size: 14px;
                padding: 8px;
            }
            select {
                background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='16' height='16'><path d='M7 10l5 5 5-5z'/></svg>");
                background-repeat: no-repeat;
                background-position: right 10px center;
                background-size: 16px;
            }
            select:hover {
                border-color: #888;
            }
            select:focus {
                border-color: #555;
                outline: none;
            }
            .labelCheckbox{
                margin-bottom: 12px !important;
            }
            .livechat-input-container {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 8px 16px 12px 16px;
                position: relative;
            }
            .livechat-input-form {
                display: flex;
                align-items: center;
                gap: 4px;
                width: 100%;
                max-width: 400px;
                background: #fff;
                padding: 4px 8px 4px 4px;
                border-radius: 12px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }
            .livechat-input {
                flex: 1;
                border: none;
                outline: none;
                padding: 10px;
                border-radius: 12px;
                font-size: 14px;
                resize: none;
                max-height: 80px;
                overflow-y: auto;
            }
            .contentMessage{
                display: flex;
                flex-direction: column;
            }
            .livechat-input.rtl {
                direction: rtl;
                text-align: right;
            }
            .livechat-file-label {
                display: flex;
                align-items: center;
                cursor: pointer;
            }
            .livechat-file-label input {
                display: none;
            }
            .livechat-file-label svg {
                fill: #666;
                transition: fill 0.3s ease;
            }
            .livechat-file-label:hover svg {
                fill: #333;
            }
            .livechat-send {
                background: none;
                border: none;
                cursor: pointer;
                padding: 5px;
                border-radius: 50%;
                transition: background 0.3s ease;
            }
            .livechat-send svg {
                transition: fill 0.3s ease;
            }
            .livechat-send:hover svg {
            }
            .iconavater,.iconCompany{
                display:none;
            }
            .agent-message .iconCompany{
                display: flex;
            }
            .visitor-message .iconavater{
                display: flex;
            }
            .livechat-messages{
                min-height: 345px;
                display: flex;
                flex-direction: column;
                overflow: auto;
                max-height: 365px;
                padding: 16px 12px 16px 16px;
            }
            .visitor-message {
                margin-left: auto;
                text-align: end;
            }
            .message-text {
                background: rgba(47, 128, 237, .2);
                background-color: #fff;
                color: #384244;
                border-radius: 8px;
                border-bottom-left-radius: 0;
                word-break: break-word;
                display: inline-block;
                word-wrap: break-word;
                white-space: pre-line;
                text-align: left;
                position: relative;
                padding: 10px 12px;
                font-weight: 400;
                line-height: 19.5px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
                max-width: 200px;
                margin-bottom: 8px;
            }
            .visitor-message .message-text{
                border-bottom-left-radius: 8px;
                border-bottom-right-radius: 0;
            }
            .message-time {
                color: #818791;
                font-size: 10px;
                left: 0;
            }
            .message-content {
                margin-bottom: 12px;
                display: flex;
                gap:6px;
            }
            .pre_chat_form .message-content {
                justify-content: center;
            }
            .visitor-message .message-content{
                flex-direction: row-reverse;
            }
            .groupAvaterMessage{
                color: var(--livechat-primary-color, #4CAF50);
                background-color: #fff;
                border-radius: 9999px;
                width: 2.5rem;
                height: 2.5rem;
                justify-content: center;
                align-items: center;
                display: flex;
                border: 1px solid #e0e0e0;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
            .message-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                flex-shrink: 0;
                overflow: hidden;
                border: 1px solid #e0e0e0;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                background-color: #fff;
            }
            .message-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .avatar-placeholder {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: var(--livechat-primary-color, #4CAF50);
                color: #fff;
                font-weight: 600;
                font-size: 14px;
            }
            @keyframes pulse {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }
                50% {
                    transform: scale(1.3);
                    opacity: 0.6;
                }
                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            .liveStatus {
                width: 20px;
                height: 20px;
                background-color: green;
                border-radius: 50%;
                position: absolute;
                top: -5px;
                display: none;
                right: 0;
                animation: pulse 1.5s infinite ease-in-out;
            }
            .livechat-container[data-position="right"] .liveStatus {
                right: auto;
                left:0;
            }
            .contentTime {
                display: flex;
                justify-content: end;
                align-items: center;
                gap: 6px;
                // width: fit-content;
            }
            .containerDialog {
                position: absolute;
                z-index: 999;
                top: 0;
                width: 100%;
                height: 100%;
            }
            .overlayContainer {
                background-color: rgba(0, 0, 0, 0.5);
                width: 100%;
                height: 100%;
            }
            .contentText {
                position: absolute;
                top: 20px;
                background-color: #fff;
                left: 0;
                right: 0;
                width: 80%;
                margin: auto;
                border-radius: 12px;
                padding: 16px;
            }
            .buttonCloseButton {
                border: 0px;
                font-family: inherit;
                width: 100%;
                max-width: 320px;
                cursor: pointer;
                display: flex;
                -webkit-box-pack: center;
                justify-content: center;
                -webkit-box-align: center;
                align-items: center;
                outline-offset: 2px;
                border-radius: 6px;
                font-size: 0.875rem;
                font-weight: bold;
                padding: 12px;
                color: rgb(255, 255, 255);
                background-color: rgb(217, 51, 17);
                margin-top: 16px;
            }
            .contentText .icon {
                display: flex;
                -webkit-box-pack: center;
                justify-content: center;
                -webkit-box-align: center;
                align-items: center;
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background-color: rgb(227, 227, 227);
                margin: 20px auto 18px;
            }
            .buttonClose {
                position: absolute;
                top: 10px;
                right: 12px;
                background: none;
                cursor: pointer;
                padding: 0;
                margin: 0;
            }
            .contentChat{
                width: 100%;
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .message-form-data {
                background-color: #fff;
                border-radius: 16px;
                padding: 16px 16px 8px 16px;
            }
            .form-responses {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .form-responses li {
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                margin-bottom: 12px;
                gap: 8px;
                color: #384244;
            }
            .pre_chat_form {
                margin: auto;
                text-align: center;
            }
            .file-preview {
                    width: 85%;
                    max-height: 180px;
                    margin-bottom: 8px;
                    object-fit: contain;
                    border-radius: 12px;
            }
            .file-info {
                display: flex;
                // border: 1px solid #a5a5a5;
                padding: 12px 16px;
                margin-bottom: 8px;
                border-radius: 8px;
                gap: 16px;
                text-decoration: none;
                color: #333;
            }
            .containerText {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            /* Files Upload Popup Styles */
            .livechat-files-popup {
                position: absolute;
                bottom: 70px;
                left: 8px;
                right: 8px;
                z-index: 100;
                background: white;
                border-radius: 16px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
                overflow: hidden;
                animation: slideUpPopup 0.3s ease-out;
                max-height: 350px;
            }

            @keyframes slideUpPopup {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .livechat-popup-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 16px;
                border-bottom: 1px solid #f0f0f0;
            }

            .livechat-collapse-btn {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f5f5f5;
                border: none;
                cursor: pointer;
                transition: background 0.2s;
            }

            .livechat-collapse-btn:hover {
                background: #e5e5e5;
            }

            .livechat-upload-status {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: #666;
            }

            .livechat-status-icon {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #22c55e;
                color: white;
            }

            .livechat-files-preview-area {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 16px;
                max-height: 200px;
                overflow-y: auto;
            }

            .livechat-file-item {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                padding: 8px;
                background: #f9fafb;
                border-radius: 12px;
            }

            .livechat-file-preview-container {
                position: relative;
                flex-shrink: 0;
            }

            .livechat-file-thumb {
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 8px;
            }

            .livechat-file-icon-preview {
                width: 60px;
                height: 60px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                font-size: 24px;
            }

            .livechat-video-preview {
                background: #1a1a2e;
                overflow: hidden;
                position: relative;
            }

            .livechat-video-preview video {
                width: 100%;
                height: 100%;
                object-fit: cover;
                opacity: 0.7;
            }

            .livechat-video-preview .livechat-file-type-icon {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            .livechat-file-info {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .livechat-file-name {
                font-size: 12px;
                font-weight: 500;
                color: #374151;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .livechat-caption-input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                font-size: 13px;
                outline: none;
                transition: border-color 0.2s;
                background: white;
            }

            .livechat-caption-input:focus {
                border-color: var(--livechat-primary-color, #22c55e);
            }

            .livechat-remove-file-btn {
                position: absolute;
                top: -6px;
                right: -6px;
                width: 20px;
                height: 20px;
                background: #ef4444;
                color: white;
                border: 2px solid white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: background 0.2s;
                padding: 0;
            }

            .livechat-remove-file-btn:hover {
                background: #dc2626;
            }

            .livechat-add-more-btn {
                min-height: 50px;
                border: 2px dashed #d1d5db;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
            }

            .livechat-add-more-btn:hover {
                border-color: #9ca3af;
                background: #f9fafb;
            }

            .livechat-send-files-btn {
                width: calc(100% - 32px);
                margin: 0 16px 16px;
                padding: 12px 20px;
                background: linear-gradient(135deg, #6600FF 0%, #9933FF 100%);
                color: white;
                border: none;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                box-shadow: 0 4px 12px rgba(102, 0, 255, 0.3);
            }

            .livechat-send-files-btn:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(102, 0, 255, 0.4);
            }

            .livechat-send-files-btn:active:not(:disabled) {
                transform: translateY(0);
            }

            .livechat-send-files-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }

            .livechat-send-files-btn svg {
                width: 18px;
                height: 18px;
            }

            .livechat-attachment-menu {
                position: absolute;
                bottom: 50px;
                right: 10px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                padding: 8px 0;
                min-width: 160px;
                z-index: 50;
                display: none;
            }

            .livechat-attachment-menu.show {
                display: block;
                animation: fadeInMenu 0.2s ease-out;
            }

            @keyframes fadeInMenu {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .livechat-attachment-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 16px;
                cursor: pointer;
                transition: background 0.2s;
                font-size: 14px;
                color: #333;
            }

            .livechat-attachment-item:hover {
                background: #f5f5f5;
            }

            .livechat-attachment-item svg {
                width: 20px;
                height: 20px;
            }

            .livechat-loader {
                width: 20px;
                height: 20px;
                border: 2px solid #fff;
                border-top-color: transparent;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* Image Modal Styles */
            .livechat-image-modal {
                display: none;
                position: fixed;
                z-index: 999999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.9);
                justify-content: center;
                align-items: center;
            }

            .livechat-image-modal.active {
                display: flex;
            }

            .livechat-modal-content {
                max-width: 90%;
                max-height: 90%;
                object-fit: contain;
                border-radius: 8px;
                transition: transform 0.3s ease;
                cursor: grab;
            }

            .livechat-modal-content:active {
                cursor: grabbing;
            }

            .livechat-modal-close {
                position: absolute;
                top: 20px;
                right: 35px;
                color: #fff;
                font-size: 40px;
                font-weight: bold;
                cursor: pointer;
                background: none;
                border: none;
                padding: 0;
                line-height: 1;
                transition: color 0.3s;
                z-index: 10;
            }

            .livechat-modal-close:hover,
            .livechat-modal-close:focus {
                color: #bbb;
            }

            .livechat-zoom-controls {
                position: absolute;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                display: flex;
                gap: 10px;
                background: rgba(0, 0, 0, 0.6);
                padding: 10px 15px;
                border-radius: 25px;
                z-index: 10;
            }

            .livechat-zoom-btn {
                background: rgba(255, 255, 255, 0.9);
                border: none;
                color: #333;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 20px;
                font-weight: bold;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }

            .livechat-zoom-btn:hover {
                background: #fff;
                transform: scale(1.1);
            }

            .livechat-zoom-btn:active {
                transform: scale(0.95);
            }

            .livechat-zoom-level {
                color: #fff;
                font-size: 14px;
                padding: 0 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 50px;
            }

            .file-preview {
                cursor: pointer;
            }

            /* Responsive styles */
            @media (max-width: 480px) {
                .livechat-window {
                    width: 100%;
                    height: 100%;
                    bottom: 0;
                    right: 0;
                    left: 0;
                    border-radius: 0;
                }
                .livechat-window[data-position="left"] {
                    left: 0;
                    right: 0;
                }
                .livechat-bubble {
                    bottom: 15px;
                    right: 15px;
                }
                .livechat-bubble[data-position="left"] {
                    left: 15px;
                    right: auto;
                }
            }

            /* Reaction Styles */
            .message-wrapper {
                position: relative;
                display: flex;
                flex-direction: column;
            }

            .message-text-container {
                position: relative;
                display: inline-block;
            }

            .reaction-trigger {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 50%;
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .reaction-trigger:hover {
                background: #f3f4f6;
                transform: scale(1.1);
            }

            .reaction-trigger svg {
                width: 16px;
                height: 16px;
                color: #6b7280;
            }

            /* Emoji Picker */
            .emoji-picker {
                position: fixed;
                background: #fff;
                border-radius: 24px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                padding: 8px 12px;
                display: flex;
                gap: 4px;
                z-index: 10000;
                animation: emojiPopIn 0.2s ease-out;
            }

            @keyframes emojiPopIn {
                from {
                    opacity: 0;
                    transform: scale(0.8) translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }

            .emoji-option {
                font-size: 22px;
                cursor: pointer;
                padding: 4px 6px;
                border-radius: 8px;
                transition: all 0.15s ease;
                line-height: 1.2;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .emoji-option:hover {
                background: #f3f4f6;
                transform: scale(1.2);
            }

            .emoji-option.active {
                background: #e0f2fe;
                border-radius: 50%;
            }

            /* Message Reactions Container */
            .message-reactions {
                position: absolute;
                bottom: -12px;
                display: flex;
                gap: 2px;
                z-index: 5;
            }

            .visitor-message .message-reactions {
                left: 8px;
            }

            .agent-message .message-reactions {
                right: 8px;
            }

            /* Message Reaction Display */
            .message-reaction {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 2px 6px;
                font-size: 14px;
                box-shadow: 0 1px 4px rgba(0,0,0,0.1);
                transition: transform 0.15s ease;
                z-index: 5;
            }

            .message-reaction.sent {
                cursor: pointer;
            }

            .message-reaction.received {
                cursor: default;
                opacity: 0.9;
            }

            .message-reaction.sent:hover {
                transform: scale(1.1);
            }

            .message-content.has-reaction {
                margin-bottom: 16px;
            }

            /* Replied Message Styles (WhatsApp-like) */
            .replied-message-container {
                background: rgba(0, 0, 0, 0.05);
                border-radius: 8px;
                padding: 8px 10px;
                margin-bottom: 6px;
                cursor: pointer;
                border-left: 3px solid var(--livechat-primary-color, #4CAF50);
                max-width: 200px;
                transition: background 0.2s ease;
            }

            .replied-message-container:hover {
                background: rgba(0, 0, 0, 0.08);
            }

            .visitor-message .replied-message-container {
                border-left-color: var(--livechat-primary-color, #2F80ED);
            }

            .replied-message-sender {
                font-size: 11px;
                font-weight: 600;
                color: var(--livechat-primary-color, #4CAF50);
                margin-bottom: 2px;
            }

            .visitor-message .replied-message-sender {
                color: var(--livechat-primary-color, #2F80ED);
            }

            .replied-message-content {
                font-size: 12px;
                color: #666;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 180px;
            }

            .replied-message-content.file-type {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .replied-message-content .file-icon {
                width: 14px;
                height: 14px;
                flex-shrink: 0;
            }

            .replied-message-thumbnail {
                width: 40px;
                height: 40px;
                border-radius: 4px;
                object-fit: cover;
                margin-top: 4px;
            }

            /* Highlight animation when scrolling to message */
            .livechat-message.highlight {
                animation: highlightMessage 2s ease-out;
            }

            @keyframes highlightMessage {
                0% {
                    background-color: rgba(79, 172, 254, 0.4);
                }
                100% {
                    background-color: transparent;
                }
            }

            /* Reply Button Styles */
            .message-actions {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                display: flex;
                gap: 4px;
                opacity: 0;
                transition: opacity 0.2s ease;
                z-index: 10;
            }

            .visitor-message .message-actions {
                left: -70px;
            }

            .agent-message .message-actions {
                right: -70px;
            }

            .message-content:hover .message-actions {
                opacity: 1;
            }

            .reply-trigger {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 50%;
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .reply-trigger:hover {
                background: #f3f4f6;
                transform: scale(1.1);
            }

            .reply-trigger svg {
                width: 16px;
                height: 16px;
                color: #6b7280;
            }

            /* Reply Bar Styles (above input) */
            .reply-bar {
                display: none;
                width: 100%;
                max-width: 400px;
                background: #f0f4ff;
                border-radius: 8px 8px 0 0;
                padding: 8px 12px;
                align-items: center;
                gap: 8px;
            }

            .reply-bar.active {
                display: flex;
            }

            .reply-bar-content {
                flex: 1;
                min-width: 0;
                border-left: 3px solid var(--livechat-primary-color, #2F80ED);
                padding-left: 10px;
            }

            .reply-bar-sender {
                font-size: 12px;
                font-weight: 600;
                color: var(--livechat-primary-color, #2F80ED);
                margin-bottom: 2px;
                text-align: left;
            }

            .reply-bar-message {
                font-size: 13px;
                color: #666;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                text-align: left;
            }

            .reply-bar-close {
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px;
                color: #6b7280;
                flex-shrink: 0;
            }

            .reply-bar-close:hover {
                color: #374151;
            }

            .reply-bar-close svg {
                width: 16px;
                height: 16px;
            }

            .livechat-input-container.has-reply .livechat-input-form {
                border-radius: 0 0 12px 12px;
            }

            .livechat-messages.has-reply {
                max-height: 320px;
                min-height: 300px;
            }
        `;

        const style = document.createElement('style');
        style.innerHTML = css;
        document.head.appendChild(style);
    }

    function formatFileSize(size) {
        if (size === undefined || size === null || isNaN(size)) {
            return 'Unknown size';
        }
        const units = ['Bytes', 'KB', 'MB', 'GB', 'TB']
        let i = 0
        while (size >= 1024 && i < units.length - 1) {
          size /= 1024
          i++
        }
        return `${size.toFixed(2)} ${units[i]}`
    }

    // Get file type category
    function getFileType(file) {
        if (file.type.startsWith('image/')) return 'image';
        if (file.type.startsWith('video/')) return 'video';
        if (file.type.startsWith('audio/')) return 'audio';
        return 'document';
    }

    // Check if text contains Arabic characters
    function isArabicText(text) {
        if (!text) return false;
        // Strip HTML tags to get clean text content
        const cleanText = text.replace(/<[^>]*>/g, '').trim();
        if (!cleanText) return false;
        // Enhanced Arabic pattern covering all Arabic Unicode ranges
        const arabicPattern = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
        return arabicPattern.test(cleanText);
    }

    // Check if file is previewable (image or video)
    function isPreviewable(type) {
        return type === 'image' || type === 'video';
    }

    // Check if file type supports caption
    function supportsCaption(type) {
        return type === 'image' || type === 'video' || type === 'document';
    }

    // Get icon for file type
    function getFileIcon(type) {
        switch (type) {
            case 'video': return 'Ã°Å¸Å½Â¥';
            case 'audio': return 'Ã°Å¸Å½Âµ';
            case 'document': return 'Ã°Å¸â€œâ€ž';
            default: return 'Ã°Å¸â€œÂ·';
        }
    }

    // Create widget DOM structure
    function createWidgetElements() {
        const container = document.createElement('div');
        container.className = 'livechat-container';

        // Create chat button (hidden until initialized)
        const button = document.createElement('button');
        button.className = 'livechat-button';
        button.style.opacity = '0';
        button.style.visibility = 'hidden';
        button.style.transition = 'opacity 0.3s ease, visibility 0.3s ease';
        const liveStatus = document.createElement('span');
        liveStatus.className = 'liveStatus';
        liveStatus.id = 'liveStatus';
        button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10a9.957 9.957 0 0 1-4.708-1.175L2 22l1.176-5.29A9.956 9.956 0 0 1 2 12C2 6.477 6.477 2 12 2zm0 2a8 8 0 0 0-8 8c0 1.335.326 2.618.94 3.766l.35.654-.656 2.946 2.948-.654.653.349A7.955 7.955 0 0 0 12 20a8 8 0 0 0 0-16zm0 3a1 1 0 0 1 1 1v2h2a1 1 0 0 1 0 2h-2v2a1 1 0 0 1-2 0v-2H9a1 1 0 0 1 0-2h2V8a1 1 0 0 1 1-1z" fill="currentColor"/></svg>`;
        button.setAttribute('aria-label', 'Open Chat');

        // Create chat window (initially hidden)
        const chatWindow = document.createElement('div');
        chatWindow.className = 'livechat-window';
        chatWindow.style.display = 'none';

        // Add elements to container
        container.appendChild(button);
        container.appendChild(liveStatus);
        container.appendChild(chatWindow);

        // Create image modal
        const imageModal = document.createElement('div');
        imageModal.className = 'livechat-image-modal';
        imageModal.innerHTML = `
            <button class="livechat-modal-close" aria-label="Close">&times;</button>
            <img class="livechat-modal-content" src="" alt="Full size image">
            <div class="livechat-zoom-controls">
                <button class="livechat-zoom-btn" id="livechat-zoom-out" aria-label="Zoom Out">âˆ’</button>
                <span class="livechat-zoom-level" id="livechat-zoom-level">100%</span>
                <button class="livechat-zoom-btn" id="livechat-zoom-in" aria-label="Zoom In">+</button>
                <button class="livechat-zoom-btn" id="livechat-zoom-reset" aria-label="Reset Zoom">âŸ²</button>
            </div>
        `;

        // Add container to page
        document.body.appendChild(container);
        document.body.appendChild(imageModal);

        // Store elements for later use
        elements = {
            container,
            button,
            chatWindow,
            imageModal
        };
        // Add button click event
        button.addEventListener('click', () => {
            document.getElementById('liveStatus').style.display = 'none';
            if (chatWindow.style.display === 'none') {
                openChat();
            } else {
                closeChat();
            }
        });

        // Add modal close event listeners
        const modalCloseBtn = imageModal.querySelector('.livechat-modal-close');
        const modalImg = imageModal.querySelector('.livechat-modal-content');
        const zoomInBtn = imageModal.querySelector('#livechat-zoom-in');
        const zoomOutBtn = imageModal.querySelector('#livechat-zoom-out');
        const zoomResetBtn = imageModal.querySelector('#livechat-zoom-reset');
        const zoomLevelDisplay = imageModal.querySelector('#livechat-zoom-level');

        let currentZoom = 1;
        const minZoom = 0.5;
        const maxZoom = 5;
        const zoomStep = 0.25;

        function updateZoom(newZoom) {
            currentZoom = Math.max(minZoom, Math.min(maxZoom, newZoom));
            modalImg.style.transform = `scale(${currentZoom})`;
            zoomLevelDisplay.textContent = `${Math.round(currentZoom * 100)}%`;
        }

        function resetZoom() {
            updateZoom(1);
        }

        modalCloseBtn.addEventListener('click', () => {
            imageModal.classList.remove('active');
            resetZoom();
        });

        // Close modal when clicking outside the image
        imageModal.addEventListener('click', (e) => {
            if (e.target === imageModal) {
                imageModal.classList.remove('active');
                resetZoom();
            }
        });

        // Zoom controls
        zoomInBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            updateZoom(currentZoom + zoomStep);
        });

        zoomOutBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            updateZoom(currentZoom - zoomStep);
        });

        zoomResetBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            resetZoom();
        });

        // Mouse wheel zoom
        modalImg.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -zoomStep : zoomStep;
            updateZoom(currentZoom + delta);
        });

        // Store zoom reset function for later use
        elements.resetZoom = resetZoom;
    }

    // Open the chat window
    function openChat() {
        if (!elements.chatWindow) return;
        elements.chatWindow.style.display = 'flex';
        state.isOpen = true;
        // If we have a session ID, load chat history
        if (state.sessionId &&  ( state.sesstionContinuation || !state.preChatFormEnabled)) {
            loadChatHistory();
            markMessageAsReceived();
            if(!state.preChatFormData.enabled){
                state.preChatFormEnabled = true;
                state.sesstionContinuation = true;
            }
        }
    }

    // Close the chat window
    function closeChat() {
        if (!elements.chatWindow) return;

        elements.chatWindow.style.display = 'none';
        state.isOpen = false;
    }

    // Load chat history
    async function loadChatHistory() {
        if (!state.sessionId) return;

        try {
            const response = await fetch(`${CHAT_API_URL}/chat-history?session_id=${state.sessionId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load chat history');
            }

            // Clear messages container
            const messagesContainer = elements.chatWindow.querySelector('.livechat-messages');
            if (messagesContainer) {
                messagesContainer.innerHTML = '';
                data.data.messages.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
                // Add messages to UI
                data.data.messages.forEach(message => {
                   if(message.content) addMessageToUI(message);
                });
                listChatHistory = data.data.messages;
                // Scroll to bottom
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } catch (error) {
            console.error('Error loading chat history:', error);
        }
    }

    // Add a message to the UI
    function addMessageToUI(message) {
        const messagesContainer = elements.chatWindow.querySelector('.livechat-messages');
        if (!messagesContainer) return;
        if (!message.sender) return;

        const messageEl = document.createElement('div');
        messageEl.className = `livechat-message  ${message.sender && message.sender.type ? message.sender.type : 'agent'}-message ${message.content.type === 'pre_chat_form' ? 'pre_chat_form' :''}`;
        messageEl.dataset.id = message.id;

        // Format timestamp
        const timestamp = new Date(message.timestamp);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Get the message's date without time
        const messageDate = new Date(timestamp);
        messageDate.setHours(0, 0, 0, 0);

        // Format time (HH:MM AM/PM)
        const formattedTime2 = timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        // Format date (Month Day, Year)
        const formattedDate = timestamp.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' });

        // Check if message is from today
        const finalOutput = messageDate.getTime() === today.getTime() ? formattedTime2 : `${formattedDate} ${formattedTime2}`;

        // Create message content based on type
        let messageContent = '';
        if (message.content) {
            if (message?.content?.type === 'text') {
                messageContent = `<div style="${message.sender.type === 'visitor' ? state.widgetData.theme_color == '#f0f0f0' ? `background-color:${state.widgetData.theme_color}`: `background-color:${state.widgetData.theme_color}20`:''}" class="message-text">${escapeHtml(message.content.text)}</div>`;
            } else if (message?.content?.type === 'file' || message?.content?.type === 'video' || message?.content?.type === 'image') {
                const isImage = message.content.mime_type && message.content.mime_type.startsWith('image/');
                const isVideo = message.content.mime_type && message.content.mime_type.startsWith('video/');
                messageContent = `
                    <div class="message-file">
                        ${isImage ? `<img src="${message.content.file_url}" alt="${message.content.file_name}" class="file-preview">` : ''}
                        ${isVideo ? `<video class="file-preview" controls> <source src="${message.content.file_url}" type="video/mp4"> ${message.content.file_name} </video>` : '' }
                        <a style="${isImage || isVideo  ? 'display: none;' :''}${message.sender.type === 'visitor' ? state.widgetData.theme_color == '#f0f0f0' ? `background-color:${state.widgetData.theme_color};`: `background-color:${state.widgetData.theme_color}20;`:''}" href="${message.content.file_url}" target="_blank" download="${message.content.file_name}" class="file-info">
                            <svg data-v-2713367e="" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-[40px] h-[40px]"><rect width="32" height="32" rx="5.33333" fill="#F0E6FF"></rect><path d="M17.3335 7.6665H11.5002C11.0581 7.6665 10.6342 7.8421 10.3217 8.15466C10.0091 8.46722 9.8335 8.89114 9.8335 9.33317V22.6665C9.8335 23.1085 10.0091 23.5325 10.3217 23.845C10.6342 24.1576 11.0581 24.3332 11.5002 24.3332H21.5002C21.9422 24.3332 22.3661 24.1576 22.6787 23.845C22.9912 23.5325 23.1668 23.1085 23.1668 22.6665V13.4998M17.3335 7.6665L23.1668 13.4998M17.3335 7.6665V13.4998H23.1668" stroke="#6600FF" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            <div class="containerText">
                            <div class="file-name">${escapeHtml(message.content.file_name)}</div>
                            <span >${formatFileSize(message.content.file_size)}</span>
                        </div></a>
                        ${message.content.caption ? `<div class="message-caption" style="font-size: 12px; color: #666; margin-top: 4px;">${escapeHtml(message.content.caption)}</div>` : ''}
                    </div>
                `;
            } else if (message?.content?.type === 'pre_chat_form') {
                const responsesList = Object.entries(message.content.responses || {}).map(([key, response]) => {
                    return `<li><strong>${escapeHtml(response.field_label || key)}</strong> ${escapeHtml(response.value)}</li>`;
                }).join('');

                messageContent = `
                    <div class="message-form-data">
                        <ul class="form-responses">
                            ${responsesList}
                        </ul>
                    </div>
                `;
            }
        }

        // Check if message has reactions (new format: array of {emoji, direction})
        const reactions = message.reactions || [];
        const hasReaction = reactions.length > 0;
        const showReactionTrigger = message.content?.type === 'text' || message.content?.type === 'file' || message.content?.type === 'image' || message.content?.type === 'video';

        // Build reactions HTML
        let reactionsHtml = '';
        if (hasReaction) {
            const reactionItems = reactions.map(r =>
                `<span class="message-reaction ${r.direction === 'SENT' ? 'sent' : 'received'}" data-message-id="${message.id}" data-direction="${r.direction}">${r.emoji}</span>`
            ).join('');
            reactionsHtml = `<div class="message-reactions">${reactionItems}</div>`;
        }

        // Build replied message HTML (WhatsApp-like quote)
        let repliedMessageHtml = '';
        if (message.replied_to_message) {
            const replied = message.replied_to_message;
            const repliedSenderName = replied.sender?.type === 'visitor' ? 'You' : (replied.sender?.name || 'Agent');
            let repliedContentHtml = '';

            if (replied.content?.type === 'text') {
                repliedContentHtml = `<div class="replied-message-content">${escapeHtml(replied.content.text)}</div>`;
            } else if (replied.content?.type === 'image') {
                repliedContentHtml = `
                    <div class="replied-message-content file-type">
                        <svg class="file-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/>
                            <path d="M21 15L16 10L5 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Photo</span>
                    </div>
                    <img class="replied-message-thumbnail" src="${replied.content.file_url}" alt="Photo">
                `;
            } else if (replied.content?.type === 'video') {
                repliedContentHtml = `
                    <div class="replied-message-content file-type">
                        <svg class="file-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="2" y="4" width="20" height="16" rx="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M10 9L15 12L10 15V9Z" fill="currentColor"/>
                        </svg>
                        <span>Video</span>
                    </div>
                `;
            } else if (replied.content?.type === 'file') {
                repliedContentHtml = `
                    <div class="replied-message-content file-type">
                        <svg class="file-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 2V8H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>${escapeHtml(replied.content.file_name || 'File')}</span>
                    </div>
                `;
            }

            repliedMessageHtml = `
                <div class="replied-message-container" data-replied-id="${replied.id}">
                    <div class="replied-message-sender">${escapeHtml(repliedSenderName)}</div>
                    ${repliedContentHtml}
                </div>
            `;
        }

        // Build the full message HTML
        messageEl.innerHTML = `
            ${message.sender.type === 'agent' && state.widgetData.show_agent_avatar ?
                `<div class="message-avatar">
                    ${message.sender.avatar ? `<img src="${message.sender.avatar}" alt="${message.sender.name}">` :
                    `<div class="avatar-placeholder">${message.sender.name.charAt(0)}</div>`}
                </div>` : ''}
            <div class="message-content${hasReaction ? ' has-reaction' : ''}">
                <div class="flex-shrink-0"><div class="flex flex-col items-center gap-1"><div class="groupAvaterMessage"><svg class="iconavater" width="18" height="18" viewBox="0 0 18 18" fill="none" class="w-5 h-5"><circle cx="9" cy="4.5" r="3" fill="currentColor"></circle><path opacity="0.5" d="M15 13.125C15 14.989 15 16.5 9 16.5C3 16.5 3 14.989 3 13.125C3 11.261 5.68629 9.75 9 9.75C12.3137 9.75 15 11.261 15 13.125Z" fill="currentColor"></path></svg> <svg class="iconCompany" width="18" height="18" viewBox="0 0 24 24" fill="none" class="w-10 h-10 w-6 h-6" role="presentation"><path fill="currentColor" d="M21.9 9.4 11.7 6.9l-.9-.2V2.6c0-1.1-.9-2-2-2H2.6c-1.1 0-2 .9-2 2v18.8c0 1.1.9 2 2 2h18.8c1.1 0 2-.9 2-2V11.3c0-.9-.6-1.7-1.5-1.9zM6.1 19.1h-.8c-.6 0-1.2-.5-1.2-1.2 0-.6.5-1.2 1.2-1.2h.8c.6 0 1.2.5 1.2 1.2 0 .6-.5 1.2-1.2 1.2zm0-5.5h-.8c-.6 0-1.2-.5-1.2-1.2 0-.6.5-1.2 1.2-1.2h.8c.6 0 1.2.5 1.2 1.2 0 .6-.5 1.2-1.2 1.2zm0-5.5h-.8c-.6 0-1.2-.5-1.2-1.2s.5-1.2 1.2-1.2h.8c.6 0 1.2.5 1.2 1.2s-.5 1.2-1.2 1.2zm6.3 11h-.8c-.6 0-1.2-.5-1.2-1.2 0-.6.5-1.2 1.2-1.2h.8c.6 0 1.2.5 1.2 1.2 0 .6-.6 1.2-1.2 1.2zm0-5.5h-.8c-.6 0-1.2-.5-1.2-1.2 0-.6.5-1.2 1.2-1.2h.8c.6 0 1.2.5 1.2 1.2 0 .6-.6 1.2-1.2 1.2zm6.3 5.5h-.8c-.6 0-1.2-.5-1.2-1.2 0-.6.5-1.2 1.2-1.2h.8c.6 0 1.2.5 1.2 1.2-.1.6-.6 1.2-1.2 1.2z"></path></svg></div></div></div>
                <div class="contentMessage">
                ${repliedMessageHtml}
                <div class="message-text-container">
                ${messageContent}
                ${showReactionTrigger ? `
                    <div class="message-actions">
                        <button class="reply-trigger" data-message-id="${message.id}" title="Reply">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 14L4 9L9 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M4 9H14C16.1217 9 18.1566 9.84285 19.6569 11.3431C21.1571 12.8434 22 14.8783 22 17V20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <button class="reaction-trigger" data-message-id="${message.id}" title="Add reaction">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M8 14C8 14 9.5 16 12 16C14.5 16 16 14 16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M9 9H9.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M15 9H15.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                ` : ''}
                ${reactionsHtml}
                </div>
                <div style="${message.content.type == 'pre_chat_form' ? 'display: none' :''}" class="${message.sender.type === 'visitor' ? 'contentTime' : ''}">
                    ${messageContent.length !== 0 ? `<div class="message-time">${finalOutput}</div>`: `` }
                   ${message.sender.type === 'visitor' ? message.status == "sent" ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 6.00293L9 17.0029L4 12.0029" stroke="#888ea8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>` : message.status == "delivered" ? `<svg viewBox="0 0 16 11" height="11" width="16" preserveAspectRatio="xMidYMid meet" class="" fill="none">
                            <path d="M11.0714 0.652832C10.991 0.585124 10.8894 0.55127 10.7667 0.55127C10.6186 0.55127 10.4916 0.610514 10.3858 0.729004L4.19688 8.36523L1.79112 6.09277C1.7488 6.04622 1.69802 6.01025 1.63877 5.98486C1.57953 5.95947 1.51817 5.94678 1.45469 5.94678C1.32351 5.94678 1.20925 5.99544 1.11192 6.09277L0.800883 6.40381C0.707784 6.49268 0.661235 6.60482 0.661235 6.74023C0.661235 6.87565 0.707784 6.98991 0.800883 7.08301L3.79698 10.0791C3.94509 10.2145 4.11224 10.2822 4.29844 10.2822C4.40424 10.2822 4.5058 10.259 4.60313 10.2124C4.70046 10.1659 4.78086 10.1003 4.84434 10.0156L11.4903 1.59863C11.5623 1.5013 11.5982 1.40186 11.5982 1.30029C11.5982 1.14372 11.5348 1.01888 11.4078 0.925781L11.0714 0.652832ZM8.6212 8.32715C8.43077 8.20866 8.2488 8.09017 8.0753 7.97168C7.99489 7.89128 7.8891 7.85107 7.75791 7.85107C7.6098 7.85107 7.4892 7.90397 7.3961 8.00977L7.10411 8.33984C7.01947 8.43717 6.97715 8.54508 6.97715 8.66357C6.97715 8.79476 7.0237 8.90902 7.1168 9.00635L8.1959 10.0791C8.33132 10.2145 8.49636 10.2822 8.69102 10.2822C8.79681 10.2822 8.89838 10.259 8.99571 10.2124C9.09304 10.1659 9.17556 10.1003 9.24327 10.0156L15.8639 1.62402C15.9358 1.53939 15.9718 1.43994 15.9718 1.32568C15.9718 1.1818 15.9125 1.05697 15.794 0.951172L15.4386 0.678223C15.3582 0.610514 15.2587 0.57666 15.1402 0.57666C14.9964 0.57666 14.8715 0.635905 14.7657 0.754395L8.6212 8.32715Z" fill="#888ea8"></path>
                        </svg>`: message.status == "read" ? `<svg viewBox="0 0 16 11" height="11" width="16" preserveAspectRatio="xMidYMid meet" class="" fill="none">
                            <path d="M11.0714 0.652832C10.991 0.585124 10.8894 0.55127 10.7667 0.55127C10.6186 0.55127 10.4916 0.610514 10.3858 0.729004L4.19688 8.36523L1.79112 6.09277C1.7488 6.04622 1.69802 6.01025 1.63877 5.98486C1.57953 5.95947 1.51817 5.94678 1.45469 5.94678C1.32351 5.94678 1.20925 5.99544 1.11192 6.09277L0.800883 6.40381C0.707784 6.49268 0.661235 6.60482 0.661235 6.74023C0.661235 6.87565 0.707784 6.98991 0.800883 7.08301L3.79698 10.0791C3.94509 10.2145 4.11224 10.2822 4.29844 10.2822C4.40424 10.2822 4.5058 10.259 4.60313 10.2124C4.70046 10.1659 4.78086 10.1003 4.84434 10.0156L11.4903 1.59863C11.5623 1.5013 11.5982 1.40186 11.5982 1.30029C11.5982 1.14372 11.5348 1.01888 11.4078 0.925781L11.0714 0.652832ZM8.6212 8.32715C8.43077 8.20866 8.2488 8.09017 8.0753 7.97168C7.99489 7.89128 7.8891 7.85107 7.75791 7.85107C7.6098 7.85107 7.4892 7.90397 7.3961 8.00977L7.10411 8.33984C7.01947 8.43717 6.97715 8.54508 6.97715 8.66357C6.97715 8.79476 7.0237 8.90902 7.1168 9.00635L8.1959 10.0791C8.33132 10.2145 8.49636 10.2822 8.69102 10.2822C8.79681 10.2822 8.89838 10.259 8.99571 10.2124C9.09304 10.1659 9.17556 10.1003 9.24327 10.0156L15.8639 1.62402C15.9358 1.53939 15.9718 1.43994 15.9718 1.32568C15.9718 1.1818 15.9125 1.05697 15.794 0.951172L15.4386 0.678223C15.3582 0.610514 15.2587 0.57666 15.1402 0.57666C14.9964 0.57666 14.8715 0.635905 14.7657 0.754395L8.6212 8.32715Z" fill="#53bdeb"></path>
                        </svg>` : `` : '' }
                    </div>
                </div>
            </div>`;

        // Add to message list
        messagesContainer.appendChild(messageEl);

        // Add click event to image previews
        const imagePreview = messageEl.querySelector('img.file-preview');
        if (imagePreview) {
            imagePreview.addEventListener('click', function() {
                const modal = elements.imageModal;
                const modalImg = modal.querySelector('.livechat-modal-content');
                modal.classList.add('active');
                modalImg.src = this.src;
            });
        }

        // Add click event to reaction trigger
        const reactionTrigger = messageEl.querySelector('.reaction-trigger');
        if (reactionTrigger) {
            reactionTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                showEmojiPicker(this, messageId);
            });
        }

        // Add click event to existing SENT reactions only (visitor can only edit their own)
        const sentReactions = messageEl.querySelectorAll('.message-reaction.sent');
        sentReactions.forEach(reaction => {
            reaction.addEventListener('click', function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                const triggerBtn = messageEl.querySelector('.reaction-trigger');
                if (triggerBtn) {
                    showEmojiPicker(triggerBtn, messageId);
                }
            });
        });

        // Add click event to replied message container (scroll to original message)
        const repliedContainer = messageEl.querySelector('.replied-message-container');
        if (repliedContainer) {
            repliedContainer.addEventListener('click', function(e) {
                e.stopPropagation();
                const repliedId = this.dataset.repliedId;
                scrollToMessage(repliedId);
            });
        }

        // Add click event to reply trigger button
        const replyTrigger = messageEl.querySelector('.reply-trigger');
        if (replyTrigger) {
            replyTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const messageId = this.dataset.messageId;
                setReplyingToMessage(message);
            });
        }

        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Scroll to a specific message by ID and highlight it
    function scrollToMessage(messageId) {
        const messagesContainer = elements.chatWindow.querySelector('.livechat-messages');
        const targetMessage = messagesContainer?.querySelector(`[data-id="${messageId}"]`);

        if (targetMessage) {
            // Scroll the message into view
            targetMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Remove any existing highlight
            const previouslyHighlighted = messagesContainer.querySelector('.livechat-message.highlight');
            if (previouslyHighlighted) {
                previouslyHighlighted.classList.remove('highlight');
            }

            // Add highlight animation
            targetMessage.classList.add('highlight');

            // Remove highlight class after animation completes
            setTimeout(() => {
                targetMessage.classList.remove('highlight');
            }, 2000);
        }
    }

    // Set the message we're replying to
    function setReplyingToMessage(message) {
        replyingToMessage = message;

        const replyBar = elements.chatWindow.querySelector('.reply-bar');
        const replyBarSender = replyBar?.querySelector('.reply-bar-sender');
        const replyBarMessage = replyBar?.querySelector('.reply-bar-message');

        if (replyBar && replyBarSender && replyBarMessage) {
            // Set sender name
            const senderName = message.sender?.type === 'visitor' ? 'You' : (message.sender?.name || 'Agent');
            replyBarSender.textContent = senderName;

            // Set message content preview
            let messagePreview = '';
            if (message.content?.type === 'text') {
                messagePreview = message.content.text;
            } else if (message.content?.type === 'image') {
                messagePreview = '📷 Photo';
            } else if (message.content?.type === 'video') {
                messagePreview = '🎥 Video';
            } else if (message.content?.type === 'file') {
                messagePreview = '📎 ' + (message.content.file_name || 'File');
            }
            replyBarMessage.textContent = messagePreview;

            // Show the reply bar
            replyBar.classList.add('active');

            // Add class to container for styling
            const inputContainer = elements.chatWindow.querySelector('.livechat-input-container');
            if (inputContainer) {
                inputContainer.classList.add('has-reply');
            }

            // Add class to messages to reduce height
            const messagesContainer = elements.chatWindow.querySelector('.livechat-messages');
            if (messagesContainer) {
                messagesContainer.classList.add('has-reply');
            }

            // Focus on input
            const textarea = elements.chatWindow.querySelector('.livechat-input');
            if (textarea) {
                textarea.focus();
            }
        }
    }

    // Clear the replying state
    function clearReplyingToMessage() {
        replyingToMessage = null;

        const replyBar = elements.chatWindow.querySelector('.reply-bar');
        if (replyBar) {
            replyBar.classList.remove('active');
        }

        // Remove class from container
        const inputContainer = elements.chatWindow.querySelector('.livechat-input-container');
        if (inputContainer) {
            inputContainer.classList.remove('has-reply');
        }

        // Remove class from messages
        const messagesContainer = elements.chatWindow.querySelector('.livechat-messages');
        if (messagesContainer) {
            messagesContainer.classList.remove('has-reply');
        }
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Available reaction emojis (like WhatsApp)
    const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    // Show emoji picker
    function showEmojiPicker(triggerElement, messageId) {
        // Close any existing emoji picker
        closeEmojiPicker();

        const triggerRect = triggerElement.getBoundingClientRect();

        // Get current SENT reaction for this message (visitor's own reaction)
        const messageEl = elements.chatWindow.querySelector(`[data-id="${messageId}"]`);
        const sentReactionEl = messageEl?.querySelector('.message-reaction.sent');
        const currentReaction = sentReactionEl?.textContent || '';

        // Create emoji picker
        const picker = document.createElement('div');
        picker.className = 'emoji-picker';
        picker.dataset.messageId = messageId;

        // Add emoji options with active state for current reaction
        picker.innerHTML = REACTION_EMOJIS.map(emoji =>
            `<span class="emoji-option${emoji === currentReaction ? ' active' : ''}" data-emoji="${emoji}">${emoji}</span>`
        ).join('');

        // Append to livechat container for proper z-index stacking
        const livechatContainer = document.querySelector('.livechat-container');
        livechatContainer.appendChild(picker);

        // Calculate position after appending to get picker dimensions
        const pickerRect = picker.getBoundingClientRect();
        const pickerWidth = pickerRect.width;

        // Position above the trigger button
        picker.style.top = (triggerRect.top - pickerRect.height - 8) + 'px';
        picker.style.left = (triggerRect.left + triggerRect.width / 2 - pickerWidth / 2) + 'px';

        // Add click events to emoji options
        picker.querySelectorAll('.emoji-option').forEach(option => {
            option.addEventListener('click', function(e) {
                e.stopPropagation();
                const emoji = this.dataset.emoji;
                // If clicking on the same emoji, remove the reaction (send empty)
                if (emoji === currentReaction) {
                    sendReaction(messageId, '');
                } else {
                    sendReaction(messageId, emoji);
                }
                closeEmojiPicker();
            });
        });

        // Close picker when clicking outside
        setTimeout(() => {
            document.addEventListener('click', handleOutsideClick);
        }, 10);
    }

    // Handle click outside emoji picker
    function handleOutsideClick(e) {
        if (!e.target.closest('.emoji-picker') && !e.target.closest('.reaction-trigger')) {
            closeEmojiPicker();
        }
    }

    // Close emoji picker
    function closeEmojiPicker() {
        const existingPicker = document.querySelector('.emoji-picker');
        if (existingPicker) {
            existingPicker.remove();
        }
        document.removeEventListener('click', handleOutsideClick);
    }

    // Send reaction to server
    async function sendReaction(messageId, emoji) {
        try {
            const response = await fetch(`${CHAT_API_URL}/send-reaction`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: state.sessionId,
                    message_id: messageId,
                    emoji: emoji,
                }),
            });

            const data = await response.json();

            if (data.success) {
                // Update the message UI with the new reaction
                updateMessageReaction(messageId, emoji);
            } else {
                console.error('Failed to send reaction:', data.message);
            }
        } catch (error) {
            console.error('Error sending reaction:', error);
        }
    }

    // Update message reaction in UI (only updates SENT reaction - visitor's own)
    function updateMessageReaction(messageId, emoji) {
        const messageEl = elements.chatWindow.querySelector(`[data-id="${messageId}"]`);
        if (!messageEl) return;

        const messageContent = messageEl.querySelector('.message-content');
        const textContainer = messageEl.querySelector('.message-text-container');

        // Get or create reactions container
        let reactionsContainer = textContainer.querySelector('.message-reactions');

        // Remove existing SENT reaction if any
        const existingSentReaction = reactionsContainer?.querySelector('.message-reaction.sent');
        if (existingSentReaction) {
            existingSentReaction.remove();
        }

        // Add new SENT reaction
        if (emoji) {
            // Create reactions container if it doesn't exist
            if (!reactionsContainer) {
                reactionsContainer = document.createElement('div');
                reactionsContainer.className = 'message-reactions';
                textContainer.appendChild(reactionsContainer);
            }

            const reactionSpan = document.createElement('span');
            reactionSpan.className = 'message-reaction sent';
            reactionSpan.dataset.messageId = messageId;
            reactionSpan.dataset.direction = 'SENT';
            reactionSpan.textContent = emoji;

            // Add click event to change reaction
            reactionSpan.addEventListener('click', function(e) {
                e.stopPropagation();
                const triggerBtn = messageEl.querySelector('.reaction-trigger');
                if (triggerBtn) {
                    showEmojiPicker(triggerBtn, messageId);
                }
            });

            reactionsContainer.appendChild(reactionSpan);
            messageContent.classList.add('has-reaction');
        } else {
            // Check if there are any remaining reactions (RECEIVED)
            const remainingReactions = reactionsContainer?.querySelectorAll('.message-reaction');
            if (!remainingReactions || remainingReactions.length === 0) {
                if (reactionsContainer) {
                    reactionsContainer.remove();
                }
                messageContent.classList.remove('has-reaction');
            }
        }

        // Update the message in listChatHistory
        if (listChatHistory) {
            const msg = listChatHistory.find(m => m.id === messageId);
            if (msg) {
                // Update reactions array
                if (!msg.reactions) {
                    msg.reactions = [];
                }
                // Remove existing SENT reaction
                msg.reactions = msg.reactions.filter(r => r.direction !== 'SENT');
                // Add new SENT reaction if emoji provided
                if (emoji) {
                    msg.reactions.push({ emoji: emoji, direction: 'SENT' });
                }
            }
        }
    }

    // Update RECEIVED reaction in UI (agent's reaction)
    function updateReceivedReaction(messageId, emoji) {
        const messageEl = elements.chatWindow.querySelector(`[data-id="${messageId}"]`);
        if (!messageEl) return;

        const messageContent = messageEl.querySelector('.message-content');
        const textContainer = messageEl.querySelector('.message-text-container');

        // Get or create reactions container
        let reactionsContainer = textContainer.querySelector('.message-reactions');

        // Remove existing RECEIVED reaction if any
        const existingReceivedReaction = reactionsContainer?.querySelector('.message-reaction.received');
        if (existingReceivedReaction) {
            existingReceivedReaction.remove();
        }

        // Add new RECEIVED reaction
        if (emoji) {
            // Create reactions container if it doesn't exist
            if (!reactionsContainer) {
                reactionsContainer = document.createElement('div');
                reactionsContainer.className = 'message-reactions';
                textContainer.appendChild(reactionsContainer);
            }

            const reactionSpan = document.createElement('span');
            reactionSpan.className = 'message-reaction received';
            reactionSpan.dataset.messageId = messageId;
            reactionSpan.dataset.direction = 'RECEIVED';
            reactionSpan.textContent = emoji;

            // Insert at beginning (RECEIVED reactions appear first)
            if (reactionsContainer.firstChild) {
                reactionsContainer.insertBefore(reactionSpan, reactionsContainer.firstChild);
            } else {
                reactionsContainer.appendChild(reactionSpan);
            }
            messageContent.classList.add('has-reaction');
        } else {
            // Check if there are any remaining reactions (SENT)
            const remainingReactions = reactionsContainer?.querySelectorAll('.message-reaction');
            if (!remainingReactions || remainingReactions.length === 0) {
                if (reactionsContainer) {
                    reactionsContainer.remove();
                }
                messageContent.classList.remove('has-reaction');
            }
        }

        // Update the message in listChatHistory
        if (listChatHistory) {
            const msg = listChatHistory.find(m => m.id === messageId);
            if (msg) {
                // Update reactions array
                if (!msg.reactions) {
                    msg.reactions = [];
                }
                // Remove existing RECEIVED reaction
                msg.reactions = msg.reactions.filter(r => r.direction !== 'RECEIVED');
                // Add new RECEIVED reaction if emoji provided
                if (emoji) {
                    msg.reactions.unshift({ emoji: emoji, direction: 'RECEIVED' });
                }
            }
        }
    }

    // Play notification sound
    function playNotificationSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (error) {
            console.log('Could not play notification sound:', error);
        }
    }

    // Initialize chat with API
    async function initializeChat() {
        try {
            if (!widgetId) {
                console.error('Widget ID is required');
                return;
            }

            // Get fingerprint
            const fingerprint = await generateFingerprint();
            // Initialize chat with API
            const response = await fetch(`${CHAT_API_URL}/initialize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    widget_id: widgetId,
                    fingerprint: fingerprint,
                    referrer: document.referrer || window.location.href,
                    browser: navigator.userAgent,
                }),
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to initialize chat');
            }

            // Store chat data
            state = {
                initialized: true,
                widgetData: data.data.widget,
                sessionId: data.data.session.id,
                sesstionContinuation :  data.data.session.is_continuation,
                contactId: data.data.contact.id,
                contactName: data.data.contact.name,
                preChatFormEnabled: data.data.pre_chat_form.enabled,
                preChatFormData: data.data.pre_chat_form,
                postChatFormData: data.data.post_chat_form,
                hasPreviousConversations: data.data.has_previous_conversations,
                isOpen: false
            };

            // Apply widget customizations
            applyWidgetCustomizations();

            // Prepare chat window
            prepareChatWindow();

            // Initialize Pusher for real-time messages
            if (window.Pusher) {
                initializePusher();
            }

            return data;
        } catch (error) {
            console.error('Error initializing chat:', error);
            throw error;
        }
    }

    // Apply widget customizations
    function applyWidgetCustomizations() {
        if (!state.widgetData) return;

        // Set theme color
        if (state.widgetData.theme_color) {
            document.documentElement.style.setProperty('--livechat-primary-color', state.widgetData.theme_color);
        }
        if (state.widgetData.theme_color === '#f0f0f0') {
            document.documentElement.style.setProperty('--livechat-primary-color', '#ffffff');
            const chatButton = elements.container.getElementsByClassName('livechat-button')[0];
            if (chatButton) {
                chatButton.style.backgroundColor = '#fff';
                chatButton.style.border = '1px solid #ddd';
                chatButton.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
                // Change SVG icon color for light theme
                const svg = chatButton.querySelector('svg');
                if (svg) {
                    svg.style.fill = '#555';
                }
            }
            // Add light-theme-chat class to chat window for avatar styling
            if (elements.chatWindow) {
                elements.chatWindow.classList.add('light-theme-chat');
            }
        }

        // Set custom logo if available
        if (state.widgetData.logo_url) {
            const button = elements.container.getElementsByClassName('livechat-button')[0];
            if (button) {
                button.style.padding = '8px';
                button.innerHTML = `<img src="${state.widgetData.logo_url}" alt="Chat" style="width: 100%; height: 100%; object-fit: contain;">`;
            }
        }

        // Set position
        if (elements.container && state.widgetData.position) {
            elements.container.dataset.position = state.widgetData.position;
            elements.chatWindow.dataset.position = state.widgetData.position;
        }

        // Show the button after initialization
        const button = elements.container.getElementsByClassName('livechat-button')[0];
        if (button) {
            button.style.opacity = '1';
            button.style.visibility = 'visible';
        }
    }

    // Prepare chat window content
    function prepareChatWindow() {
        if (!elements.chatWindow || !state.widgetData) return;

        // Create chat window content
        const content = `
            <div class="livechat-header${state.widgetData.theme_color == '#f0f0f0' ? ' light-theme' : ''}" style="${state.widgetData.theme_color == '#f0f0f0' ? 'color:#555' : ''}">
                <div class="livechat-header-info">
                    ${state.widgetData.logo_url ? `<img src="${state.widgetData.logo_url}" alt="Logo" class="livechat-logo">` : ''}
                    <div class="livechat-header-text">
                        <h3>${state.widgetData.language == 'ar' ? 'تواصل معنا عبر الدردشة' : 'Chat with us'}</h3>
                        <p class="livechat-status">${state.widgetData.language == 'ar' ? 'نحن متواجدون' : `We're online`}</p>
                    </div>
                </div>
                <div class="livechat-header-actions">
                    <button class="livechatButton livechat-minus" aria-label="Minimize Chat">&minus;</button>
                    <button class="livechatButton livechat-close" aria-label="Close Chat">&times;</button>
                </div>
            </div>

            <div class="livechat-body" style="${state.widgetData.theme_color == '#f0f0f0' ? `background-color:${state.widgetData.theme_color}`: `background-color:${state.widgetData.theme_color}20`}">
                <!-- Files Upload Popup -->
                <div class="livechat-files-popup" style="display: none;"></div>

                <div class="contentChat ${(state.preChatFormEnabled && !state.sesstionContinuation ? 'livechat-content' :'' )}">
                <div ${(state.preChatFormEnabled && !state.sesstionContinuation) ? 'style="display: none;"' : ''} class="livechat-messages"></div>

                ${renderPreChatForm(state.preChatFormData)}
                ${renderPostChatForm(state.postChatFormData)}
                ${renderDialog()}

                <div class="livechat-input-container" ${(state.preChatFormEnabled && !state.sesstionContinuation) ? 'style="display: none;"' : ''}>
                    <div class="reply-bar">
                        <div class="reply-bar-content">
                            <div class="reply-bar-sender"></div>
                            <div class="reply-bar-message"></div>
                        </div>
                        <button type="button" class="reply-bar-close">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <form class="livechat-input-form">
                        ${state.widgetData.show_file_upload ?
                            `<button type="button" class="livechat-attachment-btn" style="background: none; border: none; cursor: pointer; padding: 5px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M13.4214 3.46454C14.9835 1.90244 17.5162 1.90244 19.0783 3.46454C20.6404 5.02664 20.6404 7.5593 19.0783 9.1214L10.2394 17.9602C9.26314 18.9365 7.68022 18.9365 6.70391 17.9602C5.7276 16.9839 5.7276 15.401 6.70391 14.4247L15.5427 5.58586C15.9333 5.19534 16.5664 5.19534 16.957 5.58586C17.3475 5.97639 17.3475 6.60955 16.957 7.00008L8.11813 15.8389C7.92286 16.0342 7.92286 16.3508 8.11813 16.546C8.31339 16.7413 8.62997 16.7413 8.82523 16.546L17.6641 7.70718C18.4451 6.92613 18.4451 5.6598 17.6641 4.87876C16.883 4.09771 15.6167 4.09771 14.8356 4.87876L5.99681 13.7176C4.62997 15.0844 4.62997 17.3005 5.99681 18.6673C7.36364 20.0342 9.57972 20.0342 10.9466 18.6673L18.3712 11.2427C18.7617 10.8522 19.3949 10.8522 19.7854 11.2427C20.1759 11.6332 20.1759 12.2664 19.7854 12.6569L12.3608 20.0816C10.2129 22.2294 6.73048 22.2294 4.58259 20.0816C2.43471 17.9337 2.43471 14.4513 4.58259 12.3034L13.4214 3.46454Z" fill="#6600FF"/>
                                </svg>
                            </button>
                            <div class="livechat-attachment-menu">
                                <div class="livechat-attachment-item" data-action="file">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                    <span>File</span>
                                </div>
                                <div class="livechat-attachment-item" data-action="screenshot">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#EC4899" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>Screenshot</span>
                                </div>
                            </div>
                            <input type="file" class="livechat-file-input" style="display: none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar" multiple>`
                            : ''
                        }
                        <textarea class="livechat-input${isArabicText(state.widgetData.message_placeholder) ? ' rtl' : ''}" placeholder="${state.widgetData.message_placeholder}" rows="1"></textarea>
                        <button type="submit" class="livechat-send">
                            <svg data-v-2acd5c09="" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_175_47291)"><path d="M8.74928 11.2501L17.4993 2.50014M8.85559 11.5235L11.0457 17.1552C11.2386 17.6513 11.3351 17.8994 11.4741 17.9718C11.5946 18.0346 11.7381 18.0347 11.8587 17.972C11.9978 17.8998 12.0946 17.6518 12.2881 17.1559L17.78 3.08281C17.9547 2.63516 18.0421 2.41133 17.9943 2.26831C17.9528 2.1441 17.8553 2.04663 17.7311 2.00514C17.5881 1.95736 17.3643 2.0447 16.9166 2.21939L2.84349 7.71134C2.34759 7.90486 2.09965 8.00163 2.02739 8.14071C1.96475 8.26129 1.96483 8.40483 2.02761 8.52533C2.10004 8.66433 2.3481 8.7608 2.84422 8.95373L8.47589 11.1438C8.5766 11.183 8.62695 11.2026 8.66935 11.2328C8.70693 11.2596 8.7398 11.2925 8.7666 11.3301C8.79685 11.3725 8.81643 11.4228 8.85559 11.5235Z" stroke="#6600FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></g><defs><clipPath id="clip0_175_47291"><rect width="20" height="20" fill="#6600FF"></rect></clipPath></defs></svg>
                        </button>
                    </form>
                </div>
                </div>
            </div>
        `;

        // Set content
        elements.chatWindow.innerHTML = content;

        // Add welcome message if available
        if (state.widgetData.welcome_message) {
            addSystemMessage(state.widgetData.welcome_message);
        }

        // Add event listeners
        const closeButton = elements.chatWindow.querySelector('.livechat-close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                closeDialog();
            });
        }

        const minusButton = elements.chatWindow.querySelector('.livechat-minus');
        if (minusButton) {
            minusButton.addEventListener('click', () => {
                closeChat();
            });
        }

        // Pre-chat form submission
        const preChatForm = elements.chatWindow.querySelector('.livechat-pre-chat-form');
        if (preChatForm) {
            preChatForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitPreChatForm(preChatForm);
            });
        }

        const postChatForm = elements.chatWindow.querySelector('.livechat-post-chat-form');
        if (postChatForm) {
            postChatForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitPostChatForm(postChatForm);
            });
        }

        // Chat input form submission
        const inputForm = elements.chatWindow.querySelector('.livechat-input-form');
        if (inputForm) {
            inputForm.addEventListener('submit', (e) => {
                e.preventDefault();
                sendMessage(inputForm);
            });
        }

        // Reply bar close button
        const replyBarCloseBtn = elements.chatWindow.querySelector('.reply-bar-close');
        if (replyBarCloseBtn) {
            replyBarCloseBtn.addEventListener('click', () => {
                clearReplyingToMessage();
            });
        }

        // Attachment button and menu
        const attachmentBtn = elements.chatWindow.querySelector('.livechat-attachment-btn');
        const attachmentMenu = elements.chatWindow.querySelector('.livechat-attachment-menu');
        
        if (attachmentBtn && attachmentMenu) {
            attachmentBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                attachmentMenu.classList.toggle('show');
            });

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                attachmentMenu.classList.remove('show');
            });

            // Attachment menu items
            const attachmentItems = attachmentMenu.querySelectorAll('.livechat-attachment-item');
            attachmentItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const action = item.dataset.action;
                    attachmentMenu.classList.remove('show');
                    
                    if (action === 'file') {
                        triggerFileInput();
                    } else if (action === 'screenshot') {
                        captureScreenshot();
                    }
                });
            });
        }

        // File input change
        const fileInput = elements.chatWindow.querySelector('.livechat-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', handleFileSelect);
        }

        // Auto-resize textarea
        const textarea = elements.chatWindow.querySelector('.livechat-input');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';                
                // Auto-detect RTL for Arabic text
                if (isArabicText(this.value || this.placeholder)) {
                    this.classList.add('rtl');
                } else {
                    this.classList.remove('rtl');
                }
            });
            textarea.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendMessage(event);
                    this.style.height = 'auto';
                }
            });
        }
    }

    // Trigger file input
    function triggerFileInput() {
        const fileInput = elements.chatWindow.querySelector('.livechat-file-input');
        if (fileInput) {
            fileInput.click();
        }
    }

    // Handle file selection
    function handleFileSelect(event) {
        const files = event.target.files;
        if (!files || files.length === 0) return;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileType = getFileType(file);
            const preview = isPreviewable(fileType) ? URL.createObjectURL(file) : '';
            selectedFiles.push({ file, preview, type: fileType, caption: '' });
        }

        // Reset input value to allow selecting the same file again
        event.target.value = '';

        // Render files popup
        renderFilesPopup();
    }

    // Capture screenshot using Screen Capture API
    async function captureScreenshot() {
        try {
            // Request screen capture
            const mediaStream = await navigator.mediaDevices.getDisplayMedia({
                video: {
                    displaySurface: 'window'
                },
                audio: false
            });

            // Create video element to capture frame
            const video = document.createElement('video');
            video.srcObject = mediaStream;
            await video.play();

            // Wait a moment for the video to be ready
            await new Promise(resolve => setTimeout(resolve, 100));

            // Create canvas and capture frame
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);

            // Stop all tracks
            mediaStream.getTracks().forEach(track => track.stop());

            // Convert canvas to blob
            canvas.toBlob((blob) => {
                if (blob) {
                    const file = new File([blob], `screenshot-${Date.now()}.png`, { type: 'image/png' });
                    const preview = URL.createObjectURL(blob);
                    selectedFiles.push({ file, preview, type: 'image', caption: '' });
                    renderFilesPopup();
                }
            }, 'image/png');

        } catch (error) {
            console.error('Screenshot capture cancelled or failed:', error);
        }
    }

    // Render files upload popup
    function renderFilesPopup() {
        const popup = elements.chatWindow.querySelector('.livechat-files-popup');
        if (!popup) return;

        if (selectedFiles.length === 0) {
            popup.style.display = 'none';
            return;
        }

        popup.style.display = 'block';

        const filesHtml = selectedFiles.map((fileItem, index) => {
            let previewHtml = '';
            
            if (fileItem.type === 'image') {
                previewHtml = `<img src="${fileItem.preview}" class="livechat-file-thumb" alt="${escapeHtml(fileItem.file.name)}">`;
            } else if (fileItem.type === 'video') {
                previewHtml = `
                    <div class="livechat-file-thumb livechat-video-preview">
                        <video src="${fileItem.preview}"></video>
                        <span class="livechat-file-type-icon">Ã°Å¸Å½Â¥</span>
                    </div>
                `;
            } else {
                previewHtml = `
                    <div class="livechat-file-icon-preview">
                        <span>${getFileIcon(fileItem.type)}</span>
                    </div>
                `;
            }

            const captionHtml = supportsCaption(fileItem.type) ? `
                <input 
                    type="text" 
                    class="livechat-caption-input" 
                    placeholder="Add a caption..." 
                    data-index="${index}"
                    value="${escapeHtml(fileItem.caption || '')}"
                >
            ` : '';

            return `
                <div class="livechat-file-item" data-index="${index}">
                    <div class="livechat-file-preview-container">
                        ${previewHtml}
                        <button type="button" class="livechat-remove-file-btn" data-index="${index}">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="livechat-file-info">
                        <div class="livechat-file-name">${escapeHtml(fileItem.file.name)}</div>
                        ${captionHtml}
                    </div>
                </div>
            `;
        }).join('');

        popup.innerHTML = `
            <div class="livechat-popup-header">
                <button type="button" class="livechat-collapse-btn" title="Close">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <div class="livechat-upload-status">
                    <span>${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''} selected</span>
                    <div class="livechat-status-icon">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="livechat-files-preview-area">
                ${filesHtml}
                <div class="livechat-add-more-btn" title="Add more files">
                    <svg width="24" height="24" fill="none" stroke="#9ca3af" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
            </div>
            <button type="button" class="livechat-send-files-btn" ${isSubmittingFiles ? 'disabled' : ''}>
                ${isSubmittingFiles ? '<div class="livechat-loader"></div> Sending...' : `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Send ${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''}`}
            </button>
        `;

        // Add event listeners
        const collapseBtn = popup.querySelector('.livechat-collapse-btn');
        if (collapseBtn) {
            collapseBtn.addEventListener('click', clearSelectedFiles);
        }

        const removeButtons = popup.querySelectorAll('.livechat-remove-file-btn');
        removeButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const index = parseInt(btn.dataset.index);
                removeFile(index);
            });
        });

        const captionInputs = popup.querySelectorAll('.livechat-caption-input');
        captionInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const index = parseInt(input.dataset.index);
                if (selectedFiles[index]) {
                    selectedFiles[index].caption = input.value;
                }
            });
        });

        const addMoreBtn = popup.querySelector('.livechat-add-more-btn');
        if (addMoreBtn) {
            addMoreBtn.addEventListener('click', triggerFileInput);
        }

        const sendFilesBtn = popup.querySelector('.livechat-send-files-btn');
        if (sendFilesBtn) {
            sendFilesBtn.addEventListener('click', sendFilesMessage);
        }
    }

    // Remove file from selection
    function removeFile(index) {
        if (selectedFiles[index] && selectedFiles[index].preview) {
            URL.revokeObjectURL(selectedFiles[index].preview);
        }
        selectedFiles.splice(index, 1);
        renderFilesPopup();
    }

    // Clear all selected files
    function clearSelectedFiles() {
        selectedFiles.forEach(item => {
            if (item.preview) {
                URL.revokeObjectURL(item.preview);
            }
        });
        selectedFiles = [];
        renderFilesPopup();
    }

    // Send files message
    async function sendFilesMessage() {
        if (selectedFiles.length === 0 || isSubmittingFiles) return;

        isSubmittingFiles = true;
        renderFilesPopup();

        const filesCount = selectedFiles.length;

        // Capture reply state before clearing (only for first file)
        const repliedMessageId = replyingToMessage?.id || null;
        const repliedToMessageData = replyingToMessage ? { ...replyingToMessage } : null;

        // Clear reply state
        clearReplyingToMessage();

        try {
            // Send each file
            for (let i = 0; i < selectedFiles.length; i++) {
                const fileItem = selectedFiles[i];

                // Create temp message for UI
                const tempId = 'temp-file-' + Date.now() + '-' + i;
                addMessageToUI({
                    id: tempId,
                    sender: { type: 'visitor', name: 'You' },
                    content: {
                        type: fileItem.type,
                        file_name: fileItem.file.name,
                        file_url: fileItem.preview,
                        file_size: fileItem.file.size,
                        mime_type: fileItem.file.type,
                        caption: fileItem.caption
                    },
                    timestamp: new Date(),
                    status: 'sending',
                    replied_to_message: i === 0 ? repliedToMessageData : null // Only first file gets the reply
                });

                // Upload file
                const formData = new FormData();
                formData.append('session_id', state.sessionId);
                formData.append('content_type', 'file');
                formData.append('file', fileItem.file);
                if (fileItem.caption) {
                    formData.append('caption', fileItem.caption);
                }
                // Add replied_message_id only for first file
                if (i === 0 && repliedMessageId) {
                    formData.append('replied_message_id', repliedMessageId);
                }

                const response = await fetch(`${CHAT_API_URL}/send-message`, {
                    method: 'POST',
                    body: formData,
                });

                const data = await response.json();

                if (!data.success) {
                    console.error('Failed to send file:', data.message);
                    // Remove temp message if failed
                    const tempMessageEl = elements.chatWindow.querySelector(`[data-id="${tempId}"]`);
                    if (tempMessageEl) {
                        tempMessageEl.remove();
                    }
                } else {
                    // Replace temp message with actual message from server
                    const tempMessageEl = elements.chatWindow.querySelector(`[data-id="${tempId}"]`);
                    if (tempMessageEl) {
                        tempMessageEl.remove();
                    }
                    // Add the actual message returned from server
                    if (data.data) {
                        addMessageToUI(data.data);
                        listChatHistory.push(data.data);
                    }
                }
            }

            // Clear files after sending
            clearSelectedFiles();

        } catch (error) {
            console.error('Error sending files:', error);
            addSystemMessage('Failed to send files. Please try again.');
        } finally {
            isSubmittingFiles = false;
            renderFilesPopup();
        }
    }

    // Render pre-chat form
    function renderPreChatForm(formData) {
        if (!formData || !formData.enabled) return '';
        const fields = formData.fields.sort((a, b) => a.order - b.order).map(field => {
            let fieldHtml = '';
            const rtlClass = isArabicText(field.placeholder || field.label || '') ? ' rtl' : '';            
            switch (field.type) {
                case 'text':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <input type="text" id="field-${field.id}" name="${field.name}" placeholder="" ${field.required ? 'required' : ''}>
                        </div>
                    `;
                    break;

                case 'email':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <input type="email" id="field-${field.id}" name="${field.name}" placeholder="" ${field.required ? 'required' : ''}>
                        </div>
                    `;
                    break;

                case 'phone':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <input type="tel" id="field-${field.id}" name="${field.name}" placeholder="" ${field.required ? 'required' : ''}>
                        </div>
                    `;
                    break;

                case 'textarea':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <textarea id="field-${field.id}" name="${field.name}" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}></textarea>
                        </div>
                    `;
                    break;

                case 'dropdown':
                    field.options = JSON.parse(field.options)
                    const options = field.options ? field.options.map(option =>
                        `<option value="${option.text}">${option.text}</option>`
                    ).join('') : '';

                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <select id="field-${field.id}" name="${field.name}" ${field.required ? 'required' : ''}>
                                <option value="">Select an option</option>
                                ${options}
                            </select>
                        </div>
                    `;
                    break;

                case 'list':
                    field.options = JSON.parse(field.options)
                    const radioButtons = field.options ? field.options.map(option =>
                        `<label class="m-0 livechat-checkbox-fields">
                            <input name="${field.name}" type="${option.type}" class="${option.type == 'checkbox' ? 'form-checkbox':'form-radio'}" />
                            <span class="text-charcoal">${ option.text }</span>
                        </label>`
                    ).join('') : '';
                    fieldHtml = `
                        <div class="livechat-form-field livechat-checkbox-field${rtlClass}">
                            <label class="labelCheckbox" for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            ${radioButtons}
                        </div>
                    `;
                    break;
                case 'mlist':
                    field.options = JSON.parse(field.options)
                    const checkboxs = field.options ? field.options.map(option =>
                        `<label class="m-0 livechat-checkbox-fields">
                            <input name="${field.name}" type="${option.type}" class="${option.type == 'checkbox' ? 'form-checkbox':'form-radio'}" />
                            <span class="text-charcoal">${ option.text }</span>
                        </label>`
                    ).join('') : '';
                    fieldHtml = `
                        <div class="livechat-form-field livechat-checkbox-field${rtlClass}">
                            <label class="labelCheckbox" for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            ${checkboxs}
                        </div>
                    `;
                    break;
            }

            return fieldHtml;
        }).join('');

        const submitBtnRtl = isArabicText(formData.submit_button_text || '') ? ' rtl' : '';
        return `
            <div style="${state.preChatFormEnabled && state.sesstionContinuation ? 'display: none' :''}" class="livechat-pre-chat-form-container">
                <div class="groupAvater">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="40" height="40" rx="20" fill="#F0E6FF"></rect><path d="M28 29V27C28 25.9391 27.5786 24.9217 26.8284 24.1716C26.0783 23.4214 25.0609 23 24 23H16C14.9391 23 13.9217 23.4214 13.1716 24.1716C12.4214 24.9217 12 25.9391 12 27V29M24 15C24 17.2091 22.2091 19 20 19C17.7909 19 16 17.2091 16 15C16 12.7909 17.7909 11 20 11C22.2091 11 24 12.7909 24 15Z" stroke="#6600FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </div>
                <form class="livechat-pre-chat-form">
                    ${fields}
                    <button type="submit" class="livechat-submit-btn${submitBtnRtl}">${formData.submit_button_text || 'Start Chat'}</button>
                </form>
            </div>
        `;
    }

    // Render post-chat form
    function renderPostChatForm(formData) {
        if (!formData || !formData.enabled) return '';
        const fields = formData.fields.sort((a, b) => a.order - b.order).map(field => {
            let fieldHtml = '';
            const rtlClass = isArabicText(field.label || field.placeholder || '') ? ' rtl' : '';
            switch (field.type) {
                case 'text':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <input type="text" id="field-${field.id}" name="${field.name}" placeholder="" ${field.required ? 'required' : ''}>
                        </div>
                    `;
                    break;

                case 'email':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <input type="email" id="field-${field.id}" name="${field.name}" placeholder="" ${field.required ? 'required' : ''}>
                        </div>
                    `;
                    break;

                case 'phone':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <input type="tel" id="field-${field.id}" name="${field.name}" placeholder="" ${field.required ? 'required' : ''}>
                        </div>
                    `;
                    break;
                case 'textarea':
                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <textarea id="field-${field.id}" name="${field.name}" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}></textarea>
                        </div>
                    `;
                    break;
                case 'checkbox':
                    fieldHtml = `
                        <label>
                            <input name="${field.name}" value="true" type="checkbox" ${field.required ? 'required' : ''} class="form-checkbox" />
                            <span class="">${field.label}${field.required ? ' *' : ''}</span>
                        </label>
                    `;
                    break;
                case 'dropdown':
                    field.options = JSON.parse(field.options)
                    const options = field.options ? field.options.map(option =>
                        `<option value="${option.text}">${option.text}</option>`
                    ).join('') : '';

                    fieldHtml = `
                        <div class="livechat-form-field${rtlClass}">
                            <label for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            <select id="field-${field.id}" name="${field.name}" ${field.required ? 'required' : ''}>
                                <option value="">Select an option</option>
                                ${options}
                            </select>
                        </div>
                    `;
                    break;

                case 'list':
                    field.options = JSON.parse(field.options)
                    const radioButtons = field.options ? field.options.map(option =>
                        `<label class="m-0 livechat-checkbox-fields">
                            <input name="${field.name}" value="${ field.label }" ${field.required ? 'required' : ''} type="${option.type}" class="${option.type == 'checkbox' ? 'form-checkbox':'form-radio'}" />
                            <span class="text-charcoal">${ option.text }</span>
                        </label>`
                    ).join('') : '';
                    fieldHtml = `
                        <div class="livechat-form-field livechat-checkbox-field${rtlClass}">
                            <label class="labelCheckbox" for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            ${radioButtons}
                        </div>
                    `;
                    break;
                case 'rating':
                    field.options = JSON.parse(field.options)
                    const ratingButtons = field.options ? field.options.map(option =>
                        `<label class="m-0 livechat-checkbox-fields">
                            <input name="${field.name}" value="${ option.label || (state.widgetData.language == 'ar' ? option.label_ar : option.label_en) }" ${field.required ? 'required' : ''} type="radio" class="form-radio" />
                            <span class="text-charcoal">${ option.label ? option.label : state.widgetData.language == 'ar' ? option.label_ar : option.label_en }</span>
                        </label>`
                    ).join('') : '';
                    fieldHtml = `
                        <div class="livechat-form-field livechat-checkbox-field${rtlClass}">
                            <label class="labelCheckbox" for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            ${ratingButtons}
                        </div>
                    `;
                    break;
                case 'mlist':
                    field.options = JSON.parse(field.options)
                    const checkboxs = field.options ? field.options.map(option =>
                        `<label class="m-0 livechat-checkbox-fields">
                            <input name="${field.name}" value="${ option.text }" type="${option.type}" class="${option.type == 'checkbox' ? 'form-checkbox':'form-radio'}" />
                            <span class="text-charcoal">${ option.text }</span>
                        </label>`
                    ).join('') : '';
                    fieldHtml = `
                        <div class="livechat-form-field livechat-checkbox-field${rtlClass}">
                            <label class="labelCheckbox" for="field-${field.id}">${field.label}${field.required ? ' *' : ''}</label>
                            ${checkboxs}
                        </div>
                    `;
                    break;
            }

            return fieldHtml;
        }).join('');
        const submitBtnRtl = isArabicText(formData.submit_button_text || '') ? ' rtl' : '';
        return `
            <div style="display: none;" class="postChatContainer livechat-post-chat-form-container">
                <form class="livechat-post-chat-form" dir="${state.widgetData.language == 'ar' ? 'rtl' : 'ltr'}">
                    ${fields}
                    <button type="submit" class="livechat-submit-btn${submitBtnRtl}">${formData.submit_button_text || 'Start Chat'}</button>
                </form>
            </div>
        `;
    }

    function renderDialog() {
        return `
            <div style="display: none;" class="containerDialog">
                <div class="overlayContainer"></div>
                <div class="contentText">
                    <button type="button" aria-label="Close modal" class="buttonClose">
                        <svg width="24" height="24" color="inherit" viewBox="0 0 32 32" aria-hidden="true" class=""><path d="M17.4,16l5.3,5.3c0.4,0.4,0.4,1,0,1.4c-0.4,0.4-1,0.4-1.4,0L16,17.4l-5.3,5.3c-0.4,0.4-1,0.4-1.4,0	c-0.4-0.4-0.4-1,0-1.4l5.3-5.3l-5.3-5.3c-0.4-0.4-0.4-1,0-1.4c0.4-0.4,1-0.4,1.4,0l5.3,5.3l5.3-5.3c0.4-0.4,1-0.4,1.4,0	c0.4,0.4,0.4,1,0,1.4L17.4,16z"></path></svg>
                    </button>
                    <div class="icon">
                        <svg width="32" height="32" color="inherit" viewBox="0 0 32 32" class=""><path d="M17.6,17H7c-0.6,0-1-0.4-1-1s0.4-1,1-1h10.6l-2.3-2.3c-0.4-0.4-0.4-1,0-1.4c0.4-0.4,1-0.4,1.4,0l4,4 c0.4,0.4,0.4,1,0,1.4l-4,4c-0.4,0.4-1,0.4-1.4,0c-0.4-0.4-0.4-1,0-1.4L17.6,17L17.6,17z M8,12c0,0.6-0.4,1-1,1s-1-0.4-1-1V8.1 C6,7,7,6,8.1,6h15.8C25,6,26,7,26,8.1v15.8c0,1.2-1,2.1-2.1,2.1H8.1C7,26,6,25,6,23.9V20c0-0.6,0.4-1,1-1s1,0.4,1,1v3.9 C8,23.9,8.1,24,8.1,24h15.8c0.1,0,0.1-0.1,0.1-0.1V8.1C24,8.1,23.9,8,23.9,8H8.1C8.1,8,8,8.1,8,8.1V12z"></path></svg>
                    </div>
                    <p dir="${state.widgetData.language == 'ar' ? 'rtl' : 'ltr'}" class="">${state.widgetData.language == 'ar' ? 'هل تريد حقاً إنهاء هذه المحادثة؟': 'Do you really want to close this chat?'}</p>
                    <button type="button" class="buttonCloseButton">
                        ${state.widgetData.language == 'ar' ? 'إغلاق الدردشة' : 'Close Chat'}
                    </button>
                </div>
            </div>
        `;
    }

    document.addEventListener("click", (event) => {
        if (event.target.closest(".buttonClose")) {
            closeDialog();
        }
        if (event.target.closest(".buttonCloseButton")) {
            submitcloseChat()
            const postChatContainer = elements.chatWindow.querySelector('.postChatContainer');
            const contentChat = elements.chatWindow.querySelector('.contentChat');
            const livechatMessages = elements.chatWindow.querySelector('.livechat-messages');
            const livechatInputContainer = elements.chatWindow.querySelector('.livechat-input-container');
            contentChat.className = 'livechat-content';
            livechatMessages.style.display = 'none';
            livechatInputContainer.style.display = 'none';
            if(postChatContainer){
                postChatContainer.style.display = 'block';
            } else {
                const postChatFormContainer = elements.chatWindow.querySelector('.livechat-post-chat-form-container');
                if (postChatFormContainer) {
                    postChatFormContainer.style.display = 'none';
                }
                const preChatFormContainer = elements.chatWindow.querySelector('.livechat-pre-chat-form-container');
                if (preChatFormContainer) {
                    preChatFormContainer.style.display = 'block';
                }
            }
            closeDialog();
            state.preChatFormEnabled = true;
            state.sesstionContinuation = false;
        }
    });

    function closeDialog(){
        const preChatFormContainer = elements.chatWindow.querySelector('.containerDialog');
        if(!state.sesstionContinuation){
            closeChat();
            return
        }
        if (preChatFormContainer) {
            const computedDisplay = window.getComputedStyle(preChatFormContainer).display;
            if(computedDisplay == 'none'){
                preChatFormContainer.style.display = 'block';
            } else {
                preChatFormContainer.style.display = 'none';
            }

        }
    }

    // Add a system message
    function addSystemMessage(text) {
        const messagesContainer = elements.chatWindow.querySelector('.livechat-messages');
        if (!messagesContainer) return;
    }

    // Submit pre-chat form
    async function submitPreChatForm(form) {
        const formData = {};
        // Get all form elements including textarea
        const inputs = form.querySelectorAll('input, select, textarea');
        console.log(inputs);
        inputs.forEach((input) => {
            if (input.name) {
                if (input.type === 'checkbox') {
                    formData[input.name] = input.checked ? 'true' : 'false';
                } else if (input.type === 'radio') {
                    if (input.checked) {
                        formData[input.name] = input.value;
                    }
                } else {
                    formData[input.name] = input.value;
                }
            }
        });

        try {
            const response = await fetch(`${CHAT_API_URL}/submit-pre-chat-form`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: state.sessionId,
                    form_data: formData,
                }),
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to submit form');
            }

            const preChatFormContainer = elements.chatWindow.querySelector('.livechat-pre-chat-form-container');
            if (preChatFormContainer) {
                preChatFormContainer.style.display = 'none';
            }

            const inputContainer = elements.chatWindow.querySelector('.livechat-input-container');
            if (inputContainer) {
                inputContainer.style.display = 'block';
            }

            loadChatHistory();
            state.preChatFormEnabled = true;
            state.sesstionContinuation = true;
            const contentChat = elements.chatWindow.querySelector('.livechat-content');
            const livechatMessages = elements.chatWindow.querySelector('.livechat-messages');
            contentChat.className = 'contentChat';
            livechatMessages.style.display = 'block';
            return data;
        } catch (error) {
            console.error('Error submitting pre-chat form:', error);
            addSystemMessage('Failed to submit form. Please try again.');
        }
    }

    async function submitcloseChat() {
        const formData = {};
        try {
            const response = await fetch(`${CHAT_API_URL}/close`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: state.sessionId,
                    form_data: formData,
                }),
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to submit form');
            }
        } catch (error) {
            console.error('Error submitting pre-chat form:', error);
            addSystemMessage('Failed to submit form. Please try again.');
        }
    }

    async function submitPostChatForm(form) {
        const formData = {};
        if(form){
            new FormData(form).forEach((value, key) => {
                formData[key] = value;
            });
        }

        try {
            const response = await fetch(`${CHAT_API_URL}/submit-post-chat-form`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: state.sessionId,
                    form_data: formData,
                }),
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to submit form');
            }

            const postChatFormContainer = elements.chatWindow.querySelector('.livechat-post-chat-form-container');
            if (postChatFormContainer) {
                postChatFormContainer.style.display = 'none';
            }
            const preChatFormContainer = elements.chatWindow.querySelector('.livechat-pre-chat-form-container');
            if (preChatFormContainer) {
                preChatFormContainer.style.display = 'block';
            }
            return data;
        } catch (error) {
            console.error('Error submitting pre-chat form:', error);
            addSystemMessage('Failed to submit form. Please try again.');
        }
    }

    // Send a text message
    async function sendMessage(form) {
        const input = document.querySelector('.livechat-input');
        const message = input.value.trim();

        if (!message) return;

        let tempId = 'temp-' + Date.now();

        // Capture reply state before clearing
        const repliedMessageId = replyingToMessage?.id || null;
        const repliedToMessageData = replyingToMessage ? { ...replyingToMessage } : null;

        input.value = '';
        input.style.height = 'auto';

        // Clear reply state
        clearReplyingToMessage();

        try {
            // Build request body
            const requestBody = {
                session_id: state.sessionId,
                content_type: 'text',
                message: message,
            };

            // Add replied_message_id if replying to a message
            if (repliedMessageId) {
                requestBody.replied_message_id = repliedMessageId;
            }

            const response = await fetch(`${CHAT_API_URL}/send-message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody),
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to send message');
            }

            if (data?.data) {
                const msgData = data.data;
                let obj = {
                    id: msgData.id,
                    sender: msgData.sender,
                    content: msgData.content,
                    timestamp: msgData.timestamp,
                    status: msgData.status,
                    replied_to_message: repliedToMessageData
                };
                addMessageToUI(obj);
                listChatHistory.push(obj);
            }

            return data;
        } catch (error) {
            console.error('Error sending message:', error);

            const tempMessageEl = elements.chatWindow.querySelector(`[data-id="${tempId}"]`);
            if (tempMessageEl) {
                tempMessageEl.remove();
            }

            addSystemMessage('Failed to send message. Please try again.');
        }
    }

    // Upload a file (legacy single file upload)
    async function uploadFile(fileInput) {
        if (!fileInput.files || !fileInput.files[0]) return;

        const file = fileInput.files[0];
        const tempId = 'temp-file-' + Date.now();

        addMessageToUI({
            id: tempId,
            sender: { type: 'visitor', name: 'You' },
            content: {
                type: 'file',
                file_name: file.name,
                uploading: true
            },
            timestamp: new Date(),
        });

        try {
            const formData = new FormData();
            formData.append('session_id', state.sessionId);
            formData.append('content_type', 'file');
            formData.append('file', file);

            const response = await fetch(`${CHAT_API_URL}/send-message`, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to upload file');
            }

            // Replace temp message with actual message from server
            const tempMessageEl = elements.chatWindow.querySelector(`[data-id="${tempId}"]`);
            if (tempMessageEl) {
                tempMessageEl.remove();
            }
            if (data.data) {
                addMessageToUI(data.data);
            }

            fileInput.value = '';

            return data;
        } catch (error) {
            console.error('Error uploading file:', error);

            const tempMessageEl = elements.chatWindow.querySelector(`[data-id="${tempId}"]`);
            if (tempMessageEl) {
                tempMessageEl.remove();
            }

            addSystemMessage('Failed to upload file. Please try again.');

            fileInput.value = '';
        }
    }

    // Initialize Pusher for real-time communication
    function initializePusher() {
        if (!state.sessionId || !window.Pusher) return;

        try {
           const pusher = new Pusher('d9d7437b7fbcc73cbf92', {
                cluster: 'eu',
                encrypted: true
            });
            const channel = pusher.subscribe(`unified-messaging-${state.sessionId}`);
            channel.bind('conversation-closed', function(data) {
                const postChatContainer = elements.chatWindow.querySelector('.postChatContainer');
                if(postChatContainer){
                    const contentChat = elements.chatWindow.querySelector('.contentChat');
                    const livechatMessages = elements.chatWindow.querySelector('.livechat-messages');
                    const livechatInputContainer = elements.chatWindow.querySelector('.livechat-input-container');
                    contentChat.className = 'livechat-content';
                    livechatMessages.style.display = 'none';
                    livechatInputContainer.style.display = 'none';
                    postChatContainer.style.display = 'block';
                }
                state.preChatFormEnabled = true;
                state.sesstionContinuation = false;
            });
            channel.bind('status-update', function(data) {
                const tempMessageEl = elements.chatWindow.querySelector(`[data-id="${data.payload.message_id}"]`);
                if (tempMessageEl) {
                    let obj = listChatHistory.find(e => e.id == data.payload.message_id);
                    if(obj?.sender?.type == "visitor"){
                        obj.status = data.payload.status
                        tempMessageEl.remove();
                        addMessageToUI(obj)
                    }
                }
            });
            channel.bind('update-reaction', function(data) {
                // Update the reaction in the UI when received from server (agent's reaction = RECEIVED)
                if (data?.payload?.message_id) {
                    const emoji = data?.payload?.emoji || '';
                    const direction = data?.payload?.direction || 'RECEIVED';
                    if (direction === 'RECEIVED') {
                        updateReceivedReaction(data.payload.message_id, emoji);
                    } else {
                        updateMessageReaction(data.payload.message_id, emoji);
                    }
                    // Play notification sound
                    playNotificationSound();
                }
            });
            channel.bind('new-message', function(data) {
                state.preChatFormEnabled = true;
                state.sesstionContinuation = true;

                // Extract message - supports both old format (data.payload) and new format (data.payload.message.data)
                let messageData = data?.payload;

                // Check if it's the new ConversationMessage format
                if (messageData?.message?.data) {
                    const msg = messageData.message.data;
                    // Convert ConversationMessage format to UI format
                    messageData = {
                        id: msg.id,
                        sender: msg.sender,
                        content: {
                            type: msg.message_type,
                            text: msg.content?.text,
                            file_name: msg.content?.file_name,
                            file_url: msg.content?.preview_url,
                            file_size: msg.content?.file_size,
                            mime_type: msg.content?.mime_type,
                            caption: msg.content?.caption
                        },
                        timestamp: msg.created_at,
                        status: msg.status,
                        reactions: msg.reaction_message,
                        replied_to_message: msg.replied_message
                    };
                }

                if (!messageData) return;

                markMessageAsDeliverd(messageData.id);
                if (elements.chatWindow.style.display === 'none') {
                    document.getElementById('liveStatus').style.display = 'block';
                    elements.chatWindow.style.display = 'flex';
                }
                if (elements.chatWindow.style.display === 'flex' && isActiveTab) {
                    markMessageAsReceived(messageData.id);
                }

                // Hide pre_chat_form and post_chat_form when new message arrives
                const preChatFormContainer = elements.chatWindow.querySelector('.livechat-pre-chat-form-container');
                const postChatContainer = elements.chatWindow.querySelector('.postChatContainer');
                const livechatMessages = elements.chatWindow.querySelector('.livechat-messages');
                const livechatInputContainer = elements.chatWindow.querySelector('.livechat-input-container');
                const contentChat = elements.chatWindow.querySelector('.livechat-content');

                if (preChatFormContainer) {
                    preChatFormContainer.style.display = 'none';
                }
                if (postChatContainer) {
                    postChatContainer.style.display = 'none';
                }
                if (livechatMessages) {
                    livechatMessages.style.display = 'block';
                }
                if (livechatInputContainer) {
                    livechatInputContainer.style.display = 'block';
                }
                if (contentChat) {
                    contentChat.className = 'contentChat';
                }

                addMessageToUI(messageData);
                if (state.widgetData && state.widgetData.sound_enabled) {
                    playNotificationSound();
                }
            });

            pusherConnection = {
                pusher: pusher,
                channel: channel
            };
        } catch (error) {
            console.error('Error initializing Pusher:', error);
        }
    }

    // Mark message as received
    async function markMessageAsReceived(messageId) {
        try {
            await fetch(`${CHAT_API_URL}/mark-messages-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: state.sessionId,
                    message_ids: !messageId ? [] : [messageId],
                }),
            });
        } catch (error) {
            console.error('Error marking message as received:', error);
        }
    }

    async function markMessageAsDeliverd(messageId) {
        if (!state.sessionId || !messageId) return;

        try {
            await fetch(`${CHAT_API_URL}/mark-messages-deliverd`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: state.sessionId,
                    message_ids: [messageId],
                }),
            });
        } catch (error) {
            console.error('Error marking message as received:', error);
        }
    }

    // Play notification sound
    function playNotificationSound() {
        const audio = new Audio(`${CHAT_API_URL}/notification.mp3`);
        audio.play().catch(e => {
        });
    }

    // Generate browser fingerprint
    async function generateFingerprint() {
        const browserData = {
            userAgent: navigator.userAgent,
            language: navigator.language,
            screenResolution: `${screen.width}x${screen.height}`,
            colorDepth: screen.colorDepth,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            platform: navigator.platform,
            cookiesEnabled: navigator.cookieEnabled
        };

        const dataString = JSON.stringify(browserData);
        return hashString(dataString);
    }

    // Create a simple hash from a string
    function hashString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash |= 0;
        }
        return hash.toString(16);
    }

    // Initialize the widget
    function init(options) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => initWidget(options));
        } else {
            initWidget(options);
        }
    }

    // Add a heartbeat to keep the session alive
    function startSessionHeartbeat() {
        setInterval(async () => {
            if (state.sessionId) {
                try {
                    await fetch(`${CHAT_API_URL}/session-heartbeat`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            session_id: state.sessionId
                        }),
                    });
                } catch (error) {
                    console.error('Session heartbeat failed:', error);
                }
            }
        }, 5 * 60 * 1000);
    }

    function initWidget(options) {
        widgetId = options.widgetId;

        injectStyles();
        createWidgetElements();
        initializeChat()
            .then(() => {
                console.log('Chat widget initialized');
                startSessionHeartbeat();
                if (state.widgetData && state.widgetData.auto_open) {
                    setTimeout(() => {
                        openChat();
                    }, (state.widgetData.auto_open_delay || 0) * 1000);
                }
            })
            .catch(error => {
                console.error('Failed to initialize chat widget:', error);
            });
    }

    // Expose the API
    window.LiveChat = {
        init
    };

    function createScriptElement(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = callback;
        document.head.appendChild(script);
    }

    const scriptUrlPusher = "https://js.pusher.com/8.2.0/pusher.min.js";
    createScriptElement(scriptUrlPusher, () => {});

    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            isActiveTab = false;
        } else {
            isActiveTab = true;
            if (elements.chatWindow.style.display === 'flex') {
                markMessageAsReceived();
            }
        }
    });
})(window, document);