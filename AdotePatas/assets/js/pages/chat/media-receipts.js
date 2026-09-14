(() => {
  const loadScript = (path, onload) => {
    const script = document.createElement('script');
    script.src = new URL(path, document.baseURI).toString();
    script.async = false;
    if (onload) script.onload = onload;
    document.head.appendChild(script);
  };

  loadScript('assets/js/pages/chat/media-receipts-core.js?v=20260914-1', () => {
    loadScript('assets/js/pages/chat/attachment-viewer.js?v=20260914-1');
  });
})();
