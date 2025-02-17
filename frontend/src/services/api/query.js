import axios from 'axios';
import { interceptors } from '@/services/api/interceptors';

const query = axios.create({
  baseURL: '/api/v1',
  headers: {
    // 'App-User-Token': userToken,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  //withCredentials: true,
});

interceptors(query);

export const get = (path, conf = {}) => {
  return query.get(path, conf);
}
export const post = (path, data, conf = {}) => {
  return query.post(path, data, conf);
}

export const put = (path, data, conf = {}) => {
  return query.put(path, data, conf);
}

export const del = (path, data, conf = {}) => {
  return query.delete(path, data, conf);
}
