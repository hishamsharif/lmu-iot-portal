import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const cookieToken = document.cookie
	.split('; ')
	.find((row) => row.startsWith('XSRF-TOKEN='))
	?.split('=')[1];
const resolvedToken = csrfToken ?? (cookieToken ? decodeURIComponent(cookieToken) : undefined);

if (resolvedToken) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = resolvedToken;
}

const enableEcho = document.querySelector('meta[name="enable-echo"]')?.getAttribute('content') === 'true';

if (enableEcho) {
	window.Echo = new Echo({
		broadcaster: 'reverb',
		key: import.meta.env.VITE_REVERB_APP_KEY,
		wsHost: import.meta.env.VITE_REVERB_HOST,
		wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
		wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
		forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
		enabledTransports: ['ws', 'wss'],
		authEndpoint: '/broadcasting/auth',
		auth: resolvedToken
			? {
				  headers: {
					  'X-CSRF-TOKEN': resolvedToken,
					  'X-Requested-With': 'XMLHttpRequest',
				  },
				  withCredentials: true,
			  }
			: undefined,
	});
}
