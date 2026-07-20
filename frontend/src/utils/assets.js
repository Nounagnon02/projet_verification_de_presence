const SUPABASE_URL = 'https://kvgzlngijxrjjdvashph.supabase.co';
const BUCKET = 'presence-uac';
const BASE = `${SUPABASE_URL}/storage/v1/object/public/${BUCKET}`;

export const assets = {
  rectoratUac: `${BASE}/images/rectorat-uac.jpg`,
  logo: `${BASE}/images/logo.jpeg`,
};
