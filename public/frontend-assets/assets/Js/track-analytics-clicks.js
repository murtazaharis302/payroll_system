document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (e) {
        let el = e.target.closest('a, button, img, div');
        if (!el) return;

        let tag = el.tagName;
        let text = el.innerText || el.alt || el.title || '';
        text = text.trim().substring(0, 100); 
        let href = el.getAttribute('href') || el.getAttribute('src') || '';
        let currentURL = window.location.href;
        let userAgent = navigator.userAgent;

        function getBrowser(userAgent) {
            if (userAgent.indexOf('Chrome') !== -1 && userAgent.indexOf('Edg') === -1) return 'Chrome';
            if (userAgent.indexOf('Firefox') !== -1) return 'Firefox';
            if (userAgent.indexOf('Safari') !== -1 && userAgent.indexOf('Chrome') === -1) return 'Safari';
            if (userAgent.indexOf('Edg') !== -1) return 'Edge';
            if (userAgent.indexOf('Trident') !== -1 || userAgent.indexOf('MSIE') !== -1) return 'Internet Explorer';
            return 'Unknown';
        }
       
        fetch(window.baseUrl + '/track-click', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                element_clicked: `${tag} - ${text || href}`,
                current_url: currentURL,
                browser: getBrowser(userAgent)
            })
        });
    });
});
