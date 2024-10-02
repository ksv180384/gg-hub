import axios from 'axios';

const api = axios.create({
  // baseURL: `${import.meta.env.VITE_APP_URL}/api/v1`,
  baseURL: `http://gg-hub.local`,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  // withCredentials: true,
});


api.interceptors.request.use((config) => {

    return config;
  },
  (error) => {
    return Promise.reject(error);
  });

api.interceptors.response.use(
  function (response) {
    // При положительном ответе сервера
    return response.data;
  },
  function (error) {

    return Promise.reject(error);
  });

export default api;
