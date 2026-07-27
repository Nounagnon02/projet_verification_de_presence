import { useState, useEffect, useCallback, useRef } from 'react';
import api from '../api/axios';
import { cache, inFlight, ttlFor } from '../api/cache';

export default function useApi(url, params = {}, options = {}) {
  const { immediate = true, defaultData = null, cacheTtl } = options;

  const paramsKey = JSON.stringify(params);
  const cacheKey = `${url}|${paramsKey}`;
  const ttl = cacheTtl ?? ttlFor(url || '');

  // Une entrée en cache (même périmée) sert de valeur initiale : la page
  // s'affiche sans attendre le réseau.
  const cached = ttl > 0 ? cache.get(cacheKey) : undefined;

  const [data, setData] = useState(cached ? cached.data : defaultData);
  const [loading, setLoading] = useState(immediate && !cached);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState(cached ? cached.pagination : null);

  const abortRef = useRef(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => { mountedRef.current = false; };
  }, []);

  const fetchData = useCallback(async (overrideParams) => {
    if (!url) return;

    const key = overrideParams ? `${url}|${JSON.stringify({ ...params, ...overrideParams })}` : cacheKey;
    const entry = ttl > 0 ? cache.get(key) : undefined;
    const fresh = entry && Date.now() - entry.at < ttl;

    if (entry) {
      setData(entry.data);
      setPagination(entry.pagination);
      setLoading(false);
      if (fresh) return; // rien à revalider
    } else {
      setLoading(true);
    }

    setError(null);

    // Déduplication : deux composants demandant la même URL en même temps
    // ne déclenchent qu'un seul appel réseau.
    let request = inFlight.get(key);
    if (!request) {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      request = api
        .get(url, { params: { ...params, ...overrideParams }, signal: controller.signal })
        .finally(() => inFlight.delete(key));
      inFlight.set(key, request);
    }

    try {
      const result = (await request).data;

      let nextData;
      let nextPagination = null;

      if (result.success !== undefined) {
        if (!result.success) throw new Error(result.message || 'Une erreur est survenue');
        nextData = result.data;
        nextPagination = result.meta ?? null;
      } else {
        nextData = result.data ?? result;
      }

      if (ttl > 0) cache.set(key, { data: nextData, pagination: nextPagination, at: Date.now() });

      if (!mountedRef.current) return;
      setData(nextData);
      if (nextPagination) setPagination(nextPagination);
    } catch (err) {
      if (err.name === 'CanceledError' || err.name === 'AbortError') return;
      if (!mountedRef.current) return;
      setError(err.response?.data?.message || err.message || 'Erreur de connexion');
    } finally {
      if (mountedRef.current) setLoading(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [url, paramsKey, ttl]);

  useEffect(() => {
    if (immediate && url) fetchData();
    return () => abortRef.current?.abort();
  }, [immediate, url, fetchData]);

  const refetch = useCallback(
    (overrideParams) => {
      // Un refetch explicite doit repartir du réseau.
      cache.delete(cacheKey);
      return fetchData(overrideParams);
    },
    [fetchData, cacheKey]
  );

  return { data, loading, error, pagination, refetch, setData };
}
