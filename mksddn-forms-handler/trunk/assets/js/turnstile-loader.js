(function () {
    'use strict';

    if (window.turnstile || document.querySelector('script[data-mksddn-fh-turnstile-loader]')) {
        return;
    }

    var script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    script.async = true;
    script.defer = true;
    script.setAttribute('data-mksddn-fh-turnstile-loader', '1');
    document.head.appendChild(script);
}());
