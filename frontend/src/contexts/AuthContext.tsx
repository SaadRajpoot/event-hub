'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { User } from '@/types';
import { api, ApiError } from '@/lib/api';
import { clearClientToken, getClientToken, setClientToken } from '@/lib/auth';

interface AuthContextType {
  user: User | null;
  token: string | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  registerAttendee: (data: RegisterAttendeeData) => Promise<void>;
  registerVendor: (data: RegisterVendorData) => Promise<void>;
}

interface RegisterAttendeeData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
}

interface RegisterVendorData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  business_name: string;
  contact_email?: string;
  contact_phone?: string;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const storedToken = getClientToken();
    if (storedToken) {
      setToken(storedToken);
      api.get<{ data: User }>('/auth/me', storedToken)
        .then((res) => setUser(res.data))
        .catch(() => {
          clearClientToken();
          setToken(null);
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  const login = async (email: string, password: string) => {
    const res = await api.post<{ data: { user: User; token: string } }>('/auth/login', { email, password });
    const { user: u, token: t } = res.data;
    setClientToken(t);
    setToken(t);
    setUser(u);
  };

  const logout = async () => {
    if (token) {
      await api.post('/auth/logout', {}, token).catch(() => {});
    }
    clearClientToken();
    setToken(null);
    setUser(null);
  };

  const registerAttendee = async (data: RegisterAttendeeData) => {
    const res = await api.post<{ data: { user: User; token: string } }>('/auth/register/attendee', data);
    const { user: u, token: t } = res.data;
    setClientToken(t);
    setToken(t);
    setUser(u);
  };

  const registerVendor = async (data: RegisterVendorData) => {
    const res = await api.post<{ data: { user: User; token: string } }>('/auth/register/vendor', data);
    const { user: u, token: t } = res.data;
    setClientToken(t);
    setToken(t);
    setUser(u);
  };

  return (
    <AuthContext.Provider value={{ user, token, loading, login, logout, registerAttendee, registerVendor }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
