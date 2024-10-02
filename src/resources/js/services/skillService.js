import apiCurrentSite from '@/services/apiCurrentSite.js';

export const getSkillsAllStorage = async () => {
  return await apiCurrentSite.get('/admin/skills/get-all-storage');
}

export const transferToSite = async (params) => {
  return await apiCurrentSite.post('/admin/skills/transfer-to-site', params);
}

export const transferToSiteAll = async () => {
  return await apiCurrentSite.post('/admin/skills/transfer-to-site-all');
}

export const translate = async (params) => {
  return await apiCurrentSite.post('/admin/skills/translate', params);
}

export const translateAll = async (params) => {
  return await apiCurrentSite.post('/skills/translate-all', params);
}
