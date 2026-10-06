import { useState, useEffect } from 'react';
import { customFetch } from '@/utils/customFetch';

export interface Package {
  id: number;
  package: {
    id: number;
    car_type: string;
    count_washes: number;
    created_at: string | null;
    updated_at: string | null;
  };
  car: {
    id: number;
    user_id: number;
    model_id: number;
    plate: string;
    created_at: string;
    updated_at: string;
  };
  start_date: string;
  end_date: string;
  number_of_washes: number;
  used_washes: number;
  renewal: boolean;
}

export interface MyPackagesResponse {
  success: boolean;
  packages: Package[];
}

let packagesCache: Package[] | null = null;
let packagesInflight: Promise<Package[]> | null = null;

export function invalidatePackagesCache() {
  packagesCache = null;
  packagesInflight = null;
}

function loadPackages(): Promise<Package[]> {
  if (packagesCache) return Promise.resolve(packagesCache);
  if (packagesInflight) return packagesInflight;

  const token = localStorage.getItem('access_token');
  packagesInflight = customFetch(`${import.meta.env.VITE_API_URL}/packages/my`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    },
  })
    .then((res) => {
      if (!res.ok) throw new Error(`Ошибка загрузки: ${res.status}`);
      return res.json() as Promise<MyPackagesResponse>;
    })
    .then((data) => {
      const list = data.packages ?? [];
      packagesCache = list;
      return list;
    })
    .finally(() => {
      packagesInflight = null;
    });

  return packagesInflight;
}

export const useMyPackages = () => {
  const [packages, setPackages] = useState<Package[]>(packagesCache ?? []);
  const [isLoading, setIsLoading] = useState(packagesCache === null);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    let cancelled = false;
    loadPackages()
      .then((list) => {
        if (!cancelled) setPackages(list);
      })
      .catch((err) => {
        if (!cancelled) setError(err as Error);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return { packages, isLoading, error };
};
