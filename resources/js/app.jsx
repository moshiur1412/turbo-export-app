import React, { useState, useCallback } from 'react';
import ReactDOM from 'react-dom/client';
import { AuthProvider, useAuth } from './context/AuthContext';
import Login from './components/Login';
import ReportCreator from './components/ReportCreator';
import ReportList from './components/ReportList';
import './bootstrap';

function Dashboard() {
    const { user, logout, isAuthenticated } = useAuth();
    const [activeTab, setActiveTab] = useState('create');
    const [refreshKey, setRefreshKey] = useState(0);

    const handleReportCreated = useCallback((report) => {
        setActiveTab('history');
        setRefreshKey(k => k + 1);
    }, []);

    return (
        <div className="min-h-screen bg-gray-50">
            <nav className="bg-white shadow-sm sticky top-0 z-40">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex items-center">
                            <div className="flex-shrink-0 flex items-center gap-3">
                                <div className="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center">
                                    <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <h1 className="text-xl font-bold text-gray-800">
                                    HRM Report Engine
                                </h1>
                            </div>
                            <div className="hidden sm:ml-10 sm:flex sm:space-x-1">
                                <button
                                    onClick={() => setActiveTab('create')}
                                    className={`px-4 py-2 text-sm font-medium rounded-lg transition-all ${
                                        activeTab === 'create'
                                            ? 'text-blue-600 bg-blue-50'
                                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                                    }`}
                                >
                                    <span className="flex items-center gap-2">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Create Report
                                    </span>
                                </button>
                                <button
                                    onClick={() => setActiveTab('history')}
                                    className={`px-4 py-2 text-sm font-medium rounded-lg transition-all ${
                                        activeTab === 'history'
                                            ? 'text-blue-600 bg-blue-50'
                                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                                    }`}
                                >
                                    <span className="flex items-center gap-2">
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Report History
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="hidden md:flex items-center gap-3">
                                <div className="text-right">
                                    <div className="text-sm font-medium text-gray-700">
                                        {user?.name || user?.email || 'User'}
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        {user?.email}
                                    </div>
                                </div>
                                <div className="w-9 h-9 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                    {(user?.name || user?.email || 'U').charAt(0).toUpperCase()}
                                </div>
                            </div>
                            <button
                                onClick={logout}
                                className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all"
                                title="Logout"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {activeTab === 'create' && (
                    <div className="space-y-6">
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">Create New Report</h2>
                            <p className="mt-1 text-sm text-gray-600">
                                Search and select from 20+ report types across Salary, Attendance, Leave, and Employee categories
                            </p>
                        </div>

                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <ReportCreator onReportCreated={handleReportCreated} />
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-5 text-white">
                                <div className="text-2xl mb-2">💰</div>
                                <h3 className="font-semibold">Salary Reports</h3>
                                <p className="text-xs text-blue-100 mt-1">6 types available</p>
                            </div>
                            <div className="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-5 text-white">
                                <div className="text-2xl mb-2">📅</div>
                                <h3 className="font-semibold">Attendance</h3>
                                <p className="text-xs text-green-100 mt-1">4 types available</p>
                            </div>
                            <div className="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-5 text-white">
                                <div className="text-2xl mb-2">🏖️</div>
                                <h3 className="font-semibold">Leave Management</h3>
                                <p className="text-xs text-purple-100 mt-1">4 types available</p>
                            </div>
                            <div className="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-5 text-white">
                                <div className="text-2xl mb-2">👥</div>
                                <h3 className="font-semibold">Employee</h3>
                                <p className="text-xs text-orange-100 mt-1">4 types available</p>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'history' && (
                    <div className="space-y-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900">Report History</h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    View and download your generated reports
                                </p>
                            </div>
                            <button
                                onClick={() => setRefreshKey(k => k + 1)}
                                className="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh
                            </button>
                        </div>

                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <ReportList key={refreshKey} />
                        </div>
                    </div>
                )}
            </main>

            <footer className="bg-white border-t mt-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <p className="text-center text-sm text-gray-500">
                        HRM Report Engine powered by Laravel Queue & Redis
                    </p>
                </div>
            </footer>
        </div>
    );
}

function App() {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-500">Loading...</p>
                </div>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Login />;
    }

    return <Dashboard />;
}

const root = ReactDOM.createRoot(document.getElementById('app'));
root.render(
    <AuthProvider>
        <App />
    </AuthProvider>
);
