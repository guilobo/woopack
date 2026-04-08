import axios from 'axios';
import { appBaseUrl } from './config';

const api = axios.create({
  baseURL: `${appBaseUrl}/api`,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

export default api;
