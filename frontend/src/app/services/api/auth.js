import { post } from '@/services/api/query.js';

const login = async (params) => {
  return await post(`login`, params);
}

const registration = async (params) => {
  return await post(`register`, params);
}

const logout = async () => {
  return await post(`logout`);
}

export default {
  login,
  registration,
  logout,
};
