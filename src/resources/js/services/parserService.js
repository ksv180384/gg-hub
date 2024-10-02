import apiParser from '@/services/apiParser.js';

export const startParserInfoAllSkills = async () => {
  return await apiParser.post('/parse-skill-info');
}

export const getInfoAllSkills = async () => {
  return await apiParser.get('/skills/all');
}

