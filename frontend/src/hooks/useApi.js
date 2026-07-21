import { useState, useEffect, useCallback, useRef } from 'react';
import api from '../api/axios';

export default function useApi(url, params = {}, options = {}) {
  const { immediate = true, defaultData = null } = options;
  const [data, setData] = useState(defaultData);
  const [loading, setLoading] = useState(immediate);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(null);

  const paramsKey = JSON.stringify(params);
  const abortRef = useRef(null);

  const fetchData = useCallback(async (overrideParams) => {
    if (!url) return;
    // Annule la requête précédente si elle est encore en cours
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    setError(null);
    try {
      const mergedParams = { ...params, ...overrideParams };
      const response = await api.get(url, { params: mergedParams, signal: controller.signal });
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
      if (err.name === 'CanceledError' || err.name === 'AbortError') return;
      const message = err.response?.data?.message || err.message || 'Erreur de connexion';
      setError(message);
    } finally {
      setLoading(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [url, paramsKey]);

  useEffect(() => {
    if (immediate && url) fetchData();
    return () => abortRef.current?.abort();
  }, [immediate, url, fetchData]);

  const refetch = useCallback((overrideParams) => fetchData(overrideParams), [fetchData]);

  return { data, loading, error, pagination, refetch, setData };
}
