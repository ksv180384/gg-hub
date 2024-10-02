import axios from 'axios';

const api = axios.create({
  // baseURL: `${import.meta.env.VITE_APP_URL}/api/v1`,
  baseURL: `http://localhost:3010`,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  // withCredentials: true,
});


api.interceptors.request.use((config) => {
    // задаем jwt токен авторизации
    // const token = getAuthToken();
    // if(token){
    //     config.headers['Authorization'] = `Bearer ${token}`;
    // }

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
    // const router = useRouter();
    // Если ошибка аворизации, то затираем токен и редирект на главную
    // if(error.response.status === 401){
    //
    //   if(error.response.data.message === 'Token has expired' && store.getters["storeUser/auth_remember"]){
    //
    //     return api.post('refresh').then(res => {
    //       localStorage.setItem('user_token', res.access_token);
    //       api.defaults.headers.common['Authorization'] = 'Bearer ' + res.access_token;
    //       return api.request(error.config);
    //     }).catch((errorRefresh) => {
    //       removeUserData(store);
    //       api.defaults.headers.common['Authorization'] = '';
    //       router.push('/login');
    //       return api.request(error.config);
    //     });
    //   }
    //
    //   removeUserData(store);
    //   store.commit('setLoadPage', false);
    //   api.defaults.headers.common['Authorization'] = '';
    //   router.push('/login');
    //   return Promise.reject(error);
    // }


    // if (error.response.status === 401){
    //   router.push({ name: 'login' });
    // }
    // if (error.response.status === 404){
    //   router.push('/404');
    // }

    // if(error?.response?.status !== 422 && error.config.method !== 'get'){
    //     store.commit('setIsSiteNotWork', true);
    //     return Promise.reject(error);
    // }

    return Promise.reject(error);
  });

export default api;
