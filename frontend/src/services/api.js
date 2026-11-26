import axios from 'axios';
const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || '/api', withCredentials: true, headers: { 'Content-Type': 'application/json', Accept: 'application/json' } });
let csrfToken = null;
export const setCsrfToken = value => { csrfToken = value || null; };
api.interceptors.request.use(config => { if (['post','put','patch','delete'].includes((config.method || '').toLowerCase()) && csrfToken) config.headers['X-CSRF-Token'] = csrfToken; return config; });
api.interceptors.response.use(r => r, error => { const message = error.response?.data?.error || 'No se pudo completar la solicitud.'; return Promise.reject(new Error(message)); });
export async function bootstrapCsrf() { const { data } = await api.get('/auth/csrf'); setCsrfToken(data.csrf_token); return data.csrf_token; }
export default api;
