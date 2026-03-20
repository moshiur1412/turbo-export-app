import { useState, useEffect, useCallback } from 'react';

const CATEGORY_ICONS = {
    'Salary & Financial': '💰',
    'Attendance': '📅',
    'Leave Management': '🏖️',
    'Employee Lifecycle': '👥',
};

export default function ReportCreator({ onReportCreated }) {
    const [reportTypes, setReportTypes] = useState({});
    const [formats, setFormats] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    const [formData, setFormData] = useState({
        type: '',
        format: 'csv',
        name: '',
        filters: {
            start_date: '',
            end_date: '',
            department_ids: [],
            year: new Date().getFullYear(),
            month: new Date().getMonth() + 1,
            date: new Date().toISOString().split('T')[0],
        },
    });

    const fetchReportConfig = useCallback(async () => {
        try {
            const [typesRes, formatsRes, deptRes] = await Promise.all([
                fetch('/api/reports/types'),
                fetch('/api/reports/formats'),
                fetch('/api/departments').catch(() => ({ ok: false, json: () => ({ data: [] }) })),
            ]);

            const typesData = await typesRes.json();
            const formatsData = await formatsRes.json();
            const deptData = deptRes.ok ? await deptRes.json() : { data: [] };

            if (typesData.success) {
                setReportTypes(typesData.data);
            }
            if (formatsData.success) {
                setFormats(formatsData.data);
            }
            if (deptData.data) {
                setDepartments(deptData.data);
            }
        } catch (error) {
            console.error('Failed to fetch report config:', error);
            setError('Failed to load report configuration');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchReportConfig();
    }, [fetchReportConfig]);

    const selectedType = Object.values(reportTypes)
        .flat()
        .find(t => t.value === formData.type);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);

        if (!formData.type) {
            setError('Please select a report type');
            return;
        }

        const selectedTypeInfo = Object.values(reportTypes)
            .flat()
            .find(t => t.value === formData.type);

        if (selectedTypeInfo?.requires_date_range && (!formData.filters.start_date || !formData.filters.end_date)) {
            setError('This report requires a date range');
            return;
        }

        setSubmitting(true);

        try {
            const response = await fetch('/api/reports', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    type: formData.type,
                    format: formData.format,
                    name: formData.name || undefined,
                    filters: {
                        ...formData.filters,
                        department_ids: formData.filters.department_ids.length > 0 
                            ? formData.filters.department_ids 
                            : undefined,
                    },
                }),
            });

            const data = await response.json();

            if (data.success) {
                setFormData({
                    ...formData,
                    type: '',
                    name: '',
                    filters: {
                        start_date: '',
                        end_date: '',
                        department_ids: [],
                        year: new Date().getFullYear(),
                        month: new Date().getMonth() + 1,
                        date: new Date().toISOString().split('T')[0],
                    },
                });
                setError(null);
                onReportCreated?.(data.data);
            } else {
                setError(data.error || data.errors ? Object.values(data.errors || {}).flat().join(', ') : 'Failed to create report');
            }
        } catch (err) {
            console.error('Failed to create report:', err);
            setError('Network error. Please check your connection.');
        } finally {
            setSubmitting(false);
        }
    };

    const handleTypeChange = (type) => {
        const typeInfo = Object.values(reportTypes)
            .flat()
            .find(t => t.value === type);

        setFormData({
            ...formData,
            type,
            name: typeInfo?.label || '',
        });
    };

    if (loading) {
        return (
            <div className="flex justify-center items-center py-8">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-start">
                    <svg className="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                    </svg>
                    <span>{error}</span>
                    <button 
                        type="button" 
                        onClick={() => setError(null)}
                        className="ml-auto pl-2 text-red-500 hover:text-red-700"
                    >
                        ×
                    </button>
                </div>
            )}

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    Report Category
                </label>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    {Object.entries(reportTypes).map(([category, types]) => (
                        <div
                            key={category}
                            className={`p-3 border rounded-lg cursor-pointer transition-colors ${
                                Object.keys(reportTypes).filter(c => 
                                    reportTypes[c]?.some(t => t.value === formData.type)
                                )[0] === category
                                    ? 'border-blue-500 bg-blue-50'
                                    : 'border-gray-200 hover:border-gray-300'
                            }`}
                        >
                            <div className="text-lg mb-1">{CATEGORY_ICONS[category]}</div>
                            <div className="text-xs font-medium text-gray-700">{category}</div>
                            <div className="text-xs text-gray-500">{types.length} reports</div>
                        </div>
                    ))}
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    Report Type <span className="text-red-500">*</span>
                </label>
                <select
                    value={formData.type}
                    onChange={(e) => handleTypeChange(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
                    <option value="">Select a report type</option>
                    {Object.entries(reportTypes).map(([category, types]) => (
                        <optgroup key={category} label={category}>
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </optgroup>
                    ))}
                </select>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Export Format
                    </label>
                    <select
                        value={formData.format}
                        onChange={(e) => setFormData({ ...formData, format: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        {formats.map((format) => (
                            <option key={format.value} value={format.value}>
                                {format.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Custom Name (optional)
                    </label>
                    <input
                        type="text"
                        value={formData.name}
                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        placeholder={selectedType?.label || 'Report name'}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            {selectedType?.requires_date_range && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Start Date <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            value={formData.filters.start_date}
                            onChange={(e) => setFormData({
                                ...formData,
                                filters: { ...formData.filters, start_date: e.target.value }
                            })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            End Date <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            value={formData.filters.end_date}
                            onChange={(e) => setFormData({
                                ...formData,
                                filters: { ...formData.filters, end_date: e.target.value }
                            })}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        />
                    </div>
                </div>
            )}

            {formData.type && (
                <>
                    {departments.length > 0 && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Filter by Department (optional)
                            </label>
                            <div className="border border-gray-200 rounded-lg p-3 max-h-48 overflow-y-auto space-y-1">
                                <label className="flex items-center text-sm">
                                    <input
                                        type="checkbox"
                                        checked={formData.filters.department_ids.length === 0}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setFormData({
                                                    ...formData,
                                                    filters: { ...formData.filters, department_ids: [] }
                                                });
                                            }
                                        }}
                                        className="mr-2 rounded text-blue-600"
                                    />
                                    <span className="font-medium">All Departments</span>
                                </label>
                                {departments.map((dept) => (
                                    <label key={dept.id} className="flex items-center text-sm">
                                        <input
                                            type="checkbox"
                                            checked={formData.filters.department_ids.includes(dept.id)}
                                            onChange={(e) => {
                                                const newIds = e.target.checked
                                                    ? [...formData.filters.department_ids, dept.id]
                                                    : formData.filters.department_ids.filter(id => id !== dept.id);
                                                setFormData({
                                                    ...formData,
                                                    filters: { ...formData.filters, department_ids: newIds }
                                                });
                                            }}
                                            className="mr-2 rounded text-blue-600"
                                        />
                                        {dept.name}
                                        {dept.location && <span className="text-gray-400 ml-1">({dept.location})</span>}
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {formData.type === 'leave_balance' && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Leave Year
                            </label>
                            <select
                                value={formData.filters.year}
                                onChange={(e) => setFormData({
                                    ...formData,
                                    filters: { ...formData.filters, year: parseInt(e.target.value) }
                                })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                {[2023, 2024, 2025, 2026, 2027].map(year => (
                                    <option key={year} value={year}>{year}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    {formData.type === 'attendance_daily' && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Attendance Date
                            </label>
                            <input
                                type="date"
                                value={formData.filters.date}
                                onChange={(e) => setFormData({
                                    ...formData,
                                    filters: { ...formData.filters, date: e.target.value }
                                })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    )}
                </>
            )}

            <div className="flex justify-end">
                <button
                    type="submit"
                    disabled={submitting || !formData.type}
                    className="px-6 py-2 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center"
                >
                    {submitting ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Creating...
                        </>
                    ) : (
                        'Create Report'
                    )}
                </button>
            </div>
        </form>
    );
}
