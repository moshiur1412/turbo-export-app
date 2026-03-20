import React, { useState, useCallback } from 'react';
import ReactDOM from 'react-dom/client';
import ReportCreator from './components/ReportCreator';
import ReportList from './components/ReportList';
import './bootstrap';

function App() {
    const [activeTab, setActiveTab] = useState('create');
    const [refreshKey, setRefreshKey] = useState(0);

    const handleReportCreated = useCallback((report) => {
        setActiveTab('history');
        setRefreshKey(k => k + 1);
    }, []);

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="bg-white shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <h1 className="text-xl font-bold text-gray-800">
                                    HRM Report Engine
                                </h1>
                            </div>
                            <div className="hidden sm:ml-8 sm:flex sm:space-x-4">
                                <button
                                    onClick={() => setActiveTab('create')}
                                    className={`px-4 py-2 text-sm font-medium ${
                                        activeTab === 'create'
                                            ? 'text-blue-600 border-b-2 border-blue-600'
                                            : 'text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    Create Report
                                </button>
                                <button
                                    onClick={() => setActiveTab('history')}
                                    className={`px-4 py-2 text-sm font-medium ${
                                        activeTab === 'history'
                                            ? 'text-blue-600 border-b-2 border-blue-600'
                                            : 'text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    Report History
                                </button>
                            </div>
                        </div>
                        <div className="flex items-center">
                            <span className="text-sm text-gray-500">
                                {new Date().toLocaleDateString()}
                            </span>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {activeTab === 'create' && (
                    <div className="space-y-8">
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">Create New Report</h2>
                            <p className="mt-1 text-sm text-gray-600">
                                Select from 20+ report types across Salary, Attendance, Leave, and Employee categories
                            </p>
                        </div>

                        <div className="bg-white shadow rounded-lg p-6">
                            <ReportCreator onReportCreated={handleReportCreated} />
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                                <div className="text-3xl mb-2">💰</div>
                                <h3 className="font-semibold text-lg">Salary Reports</h3>
                                <p className="text-sm text-blue-100 mt-1">
                                    6 types including Master Sheet, Department, Designation, Location, Comparative, and Bank Advice
                                </p>
                            </div>
                            <div className="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-6 text-white">
                                <div className="text-3xl mb-2">📅</div>
                                <h3 className="font-semibold text-lg">Attendance Reports</h3>
                                <p className="text-sm text-green-100 mt-1">
                                    4 types including Daily, Monthly, Late Trends, and Overtime summaries
                                </p>
                            </div>
                            <div className="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                                <div className="text-3xl mb-2">🏖️</div>
                                <h3 className="font-semibold text-lg">Leave Reports</h3>
                                <p className="text-sm text-purple-100 mt-1">
                                    4 types including Balance, Encashment, Heatmap, and Availed reports
                                </p>
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
                                className="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Refresh
                            </button>
                        </div>

                        <div className="bg-white shadow rounded-lg overflow-hidden">
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

const root = ReactDOM.createRoot(document.getElementById('app'));
root.render(<App />);
