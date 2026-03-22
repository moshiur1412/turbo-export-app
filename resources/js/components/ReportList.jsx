import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

const STATUS_CONFIG = {
    pending: { bg: 'bg-amber-100', text: 'text-amber-800', icon: '⏳' },
    processing: { bg: 'bg-blue-100', text: 'text-blue-800', icon: '⚙️' },
    completed: { bg: 'bg-emerald-100', text: 'text-emerald-800', icon: '✅' },
    failed: { bg: 'bg-rose-100', text: 'text-rose-800', icon: '❌' },
    cancelled: { bg: 'bg-gray-100', text: 'text-gray-800', icon: '🚫' },
};

const FORMAT_CONFIG = {
    csv: { bg: 'bg-green-100', text: 'text-green-700' },
    xlsx: { bg: 'bg-emerald-100', text: 'text-emerald-700' },
    pdf: { bg: 'bg-red-100', text: 'text-red-700' },
    docx: { bg: 'bg-blue-100', text: 'text-blue-700' },
    sql: { bg: 'bg-purple-100', text: 'text-purple-700' },
};

export default function ReportList({ userId = 1, onRefresh }) {
    const [reports, setReports] = useState([]);
    const [loading, setLoading] = useState(true);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [total, setTotal] = useState(0);

    const fetchReports = useCallback(async (page = 1) => {
        setLoading(true);
        try {
            const response = await axios.get(`/api/reports?page=${page}&per_page=10`);
            const data = response.data;
            
            if (data.success) {
                setReports(data.data);
                setCurrentPage(data.meta.current_page);
                setTotalPages(data.meta.last_page);
                setTotal(data.meta.total);
            }
        } catch (error) {
            console.error('Failed to fetch reports:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchReports();
    }, [fetchReports, onRefresh]);

    useEffect(() => {
        const processingReports = reports.filter(r => 
            r.status === 'pending' || r.status === 'processing'
        );
        
        if (processingReports.length === 0) return;

        const interval = setInterval(() => {
            processingReports.forEach(async (report) => {
                try {
                    const response = await axios.get(`/api/reports/${report.id}/progress`);
                    const result = response.data;
                    
                    if (result.success && result.data) {
                        const data = result.data;
                        if (data.status !== report.status) {
                            setReports(prev => prev.map(r => 
                                r.id === report.id ? { ...r, ...data } : r
                            ));
                        }
                    }
                } catch (error) {
                    console.error('Failed to poll progress:', error);
                }
            });
        }, 2000);

        return () => clearInterval(interval);
    }, [reports]);

    const handleDelete = async (reportId) => {
        if (!confirm('Are you sure you want to delete this report?')) return;

        try {
            const response = await axios.delete(`/api/reports/${reportId}`);
            const data = response.data;

            if (data.success) {
                setReports(reports.filter(r => r.id !== reportId));
                setTotal(prev => prev - 1);
            }
        } catch (error) {
            console.error('Failed to delete report:', error);
        }
    };

    const handleRetry = async (reportId) => {
        try {
            const response = await axios.post(`/api/reports/${reportId}/retry`);
            const data = response.data;

            if (data.success) {
                fetchReports(currentPage);
            }
        } catch (error) {
            console.error('Failed to retry report:', error);
        }
    };

    const handleDownload = async (report) => {
        try {
            const response = await axios.get(`/api/reports/${report.id}/download`, {
                responseType: 'blob',
            });

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', report.file_name || `report-${report.id}.${report.format}`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Failed to download report:', error);
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatFileSize = (bytes) => {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    };

    const renderPagination = () => {
        if (totalPages <= 1) return null;

        const pages = [];
        const showPages = 5;
        let start = Math.max(1, currentPage - Math.floor(showPages / 2));
        let end = Math.min(totalPages, start + showPages - 1);

        if (end - start + 1 < showPages) {
            start = Math.max(1, end - showPages + 1);
        }

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        return (
            <div className="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
                <div className="flex justify-between items-center w-full">
                    <div className="text-sm text-gray-700">
                        Showing <span className="font-medium">{(currentPage - 1) * 10 + 1}</span> to{' '}
                        <span className="font-medium">{Math.min(currentPage * 10, total)}</span> of{' '}
                        <span className="font-medium">{total}</span> results
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => fetchReports(currentPage - 1)}
                            disabled={currentPage === 1}
                            className="p-2 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        {pages.map(page => (
                            <button
                                key={page}
                                onClick={() => fetchReports(page)}
                                className={`w-9 h-9 text-sm border rounded-lg transition-colors ${
                                    page === currentPage 
                                        ? 'bg-blue-600 text-white border-blue-600' 
                                        : 'hover:bg-gray-50'
                                }`}
                            >
                                {page}
                            </button>
                        ))}
                        <button
                            onClick={() => fetchReports(currentPage + 1)}
                            disabled={currentPage === totalPages}
                            className="p-2 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition-colors"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        );
    };

    if (loading && reports.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-16">
                <div className="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <p className="mt-4 text-gray-600">Loading reports...</p>
            </div>
        );
    }

    if (reports.length === 0) {
        return (
            <div className="text-center py-16 px-4">
                <div className="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <svg className="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 className="text-lg font-medium text-gray-900">No reports yet</h3>
                <p className="mt-1 text-gray-500">Get started by creating your first report.</p>
            </div>
        );
    }

    return (
        <div className="overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Report
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Format
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Progress
                            </th>
                            <th className="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Created
                            </th>
                            <th className="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {reports.map((report) => {
                            const statusStyle = STATUS_CONFIG[report.status] || STATUS_CONFIG.pending;
                            const formatStyle = FORMAT_CONFIG[report.format] || FORMAT_CONFIG.csv;
                            
                            return (
                                <tr key={report.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center">
                                            <div className="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                                <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <div className="text-sm font-medium text-gray-900">{report.name}</div>
                                                {report.file_name && (
                                                    <div className="text-xs text-gray-500">{report.file_size_formatted}</div>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="text-sm text-gray-900">{report.type_label || report.type}</div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${formatStyle.bg} ${formatStyle.text}`}>
                                            {report.format?.toUpperCase()}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${statusStyle.bg} ${statusStyle.text}`}>
                                            <span>{statusStyle.icon}</span>
                                            <span>{report.status_label || report.status}</span>
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {(report.status === 'processing' || report.status === 'pending') ? (
                                            <div className="flex items-center gap-2">
                                                <div className="w-24 bg-gray-200 rounded-full h-2">
                                                    <div
                                                        className="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                                        style={{ width: `${report.progress || 0}%` }}
                                                    ></div>
                                                </div>
                                                <span className="text-xs text-gray-500 w-10">{report.progress || 0}%</span>
                                            </div>
                                        ) : (
                                            <span className="text-sm text-gray-500">
                                                {report.total_records ? `${report.total_records.toLocaleString()} records` : '-'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {formatDate(report.created_at)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        {report.status === 'completed' && (
                                            <button
                                                onClick={() => handleDownload(report)}
                                                className="text-blue-600 hover:text-blue-900 mr-4 inline-flex items-center gap-1"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                                Download
                                            </button>
                                        )}
                                        {report.status === 'failed' && (
                                            <button
                                                onClick={() => handleRetry(report.id)}
                                                className="text-amber-600 hover:text-amber-900 mr-4 inline-flex items-center gap-1"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Retry
                                            </button>
                                        )}
                                        <button
                                            onClick={() => handleDelete(report.id)}
                                            className="text-rose-600 hover:text-rose-900 inline-flex items-center gap-1"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            
            {renderPagination()}
        </div>
    );
}
