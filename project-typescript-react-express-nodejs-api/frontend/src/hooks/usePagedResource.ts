"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { apiRequest } from "@/services/api";

export interface PaginationInfo {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export function usePagedResource<T>(endpoint: string, role: string, search: string, enabled = true, limit = 25) {
  const [query, setQuery] = useState({ search, page: 1 });
  const [data, setData] = useState<T[]>([]);
  const [pagination, setPagination] = useState<PaginationInfo>({ page: 1, limit, total: 0, totalPages: 1 });
  const [resolvedKey, setResolvedKey] = useState("");
  const queryKey = JSON.stringify([endpoint, role, query, limit]);
  const loading = enabled && resolvedKey !== queryKey;
  const [error, setError] = useState("");
  const requestId = useRef(0);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setQuery(previous => previous.search === search ? previous : { search, page: 1 });
    }, 250);
    return () => window.clearTimeout(timer);
  }, [search]);

  const refresh = useCallback((signal?: AbortSignal) => {
    if (!enabled) return Promise.resolve();
    const id = ++requestId.current;
    const params = new URLSearchParams({ page: String(query.page), limit: String(limit), search: query.search });
    return apiRequest<T[]>(`${endpoint}?${params}`, { signal }, role).then(result => {
      if (signal?.aborted || id !== requestId.current) return;
      if (result.success) {
        setData(result.data ?? []);
        setPagination(result.pagination);
        setError("");
      } else {
        setError(result.message || result.error || "ไม่สามารถโหลดข้อมูลได้");
      }
      setResolvedKey(queryKey);
    });
  }, [enabled, endpoint, limit, query, queryKey, role]);

  useEffect(() => {
    const controller = new AbortController();
    void refresh(controller.signal);
    const onReset = () => { void refresh(controller.signal); };
    window.addEventListener("demo-data-reset", onReset);
    return () => {
      controller.abort();
      window.removeEventListener("demo-data-reset", onReset);
    };
  }, [refresh]);

  const setPage = useCallback((page: number) => setQuery(previous => ({ ...previous, page })), []);
  return { data, pagination, setPage, refresh, loading, error };
}
