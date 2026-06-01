import { useState, useEffect, useCallback } from 'react';
import api from '../api/axios';

export default function useApi(url, params = {}, options = {}) {
  const { immediate = true, defaultData = null } = options;
  const [data, setData] = useState(defaultData);
  const [loading, setLoading] = useState(immediate);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(null);

  const paramsKey = JSON.stringify(params);

  const fetchData = useCallback(async (overrideParams) => {
    if (!url) return;
    setLoading(true);
    setError(null);
    try {
      const mergedParams = { ...params, ...overrideParams };
      const response = await api.get(url, { params: mergedParams });
      const result = response.data;

      if (result.success !== undefined) {
        if (result.success) {
          setData(result.data);
          if (result.meta) setPagination(result.meta);
        } else {
          setError(result.message || 'Une erreur est survenue');
        }
      } else if (result.data) {
        setData(result.data);
      } else {
        setData(result);
      }
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Erreur de connexion';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [url, paramsKey]);

  useEffect(() => {
    if (immediate && url) {
      fetchData();
    }
  }, [immediate, url, fetchData]);

  const refetch = useCallback((overrideParams) => {
    return fetchData(overrideParams);
  }, [fetchData]);

  return { data, loading, error, pagination, refetch, setData };
}
