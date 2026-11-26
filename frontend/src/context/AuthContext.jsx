import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import api, { bootstrapCsrf, setCsrfToken } from '../services/api';
const AuthContext = createContext(null);
export function AuthProvider({ children }) {
  const [user,setUser]=useState(null); const [loading,setLoading]=useState(true);
  useEffect(()=>{ let active=true; (async()=>{ try { await bootstrapCsrf(); const {data}=await api.get('/auth/me'); if(active){setUser(data.user);setCsrfToken(data.csrf_token);} } catch { if(active)setUser(null); } finally {if(active)setLoading(false);} })(); return ()=>{active=false}; },[]);
  const login=async credentials=>{const {data}=await api.post('/auth/login',credentials);setCsrfToken(data.csrf_token);setUser(data.user);};
  const register=async values=>{const {data}=await api.post('/auth/register',values);setCsrfToken(data.csrf_token);setUser(data.user);};
  const logout=async()=>{try{await api.post('/auth/logout');}finally{setUser(null);setCsrfToken(null);}};
  const value=useMemo(()=>({user,loading,login,register,logout}),[user,loading]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
export const useAuth=()=>useContext(AuthContext);
