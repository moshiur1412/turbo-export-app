import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import axios from 'axios';

const AuthContext = createContext(null);

const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [token, setToken] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const storedToken = localStorage.getItem(TOKEN_KEY);
        const storedUser = localStorage.getItem(USER_KEY);
        
        if (storedToken && storedUser) {
            setToken(storedToken);
            setUser(JSON.parse(storedUser));
            axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`;
        }
        setLoading(false);
    }, []);

    const login = useCallback(async (email, password) => {
        setError(null);
        setLoading(true);
        
        try {
            const response = await axios.post('/api/login', { email, password });
            
            if (response.data.token) {
                const { token: newToken, user: userData } = response.data;
                
                localStorage.setItem(TOKEN_KEY, newToken);
                localStorage.setItem(USER_KEY, JSON.stringify(userData));
                
                setToken(newToken);
                setUser(userData);
                axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
                
                return true;
            }
        } catch (err) {
            const errorMessage = err.response?.data?.error || 'Login failed';
            setError(errorMessage);
            return false;
        } finally {
            setLoading(false);
        }
    }, []);

    const logout = useCallback(async () => {
        try {
            await axios.post('/api/logout');
        } catch (err) {
            console.error('Logout error:', err);
        } finally {
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem(USER_KEY);
            delete axios.defaults.headers.common['Authorization'];
            setToken(null);
            setUser(null);
        }
    }, []);

    const value = {
        user,
        token,
        loading,
        error,
        login,
        logout,
        isAuthenticated: !!token,
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}

export function apiClient() {
    return axios.create({
        baseURL: '/api',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': token ? `Bearer ${token}` : '',
        },
    });
}
