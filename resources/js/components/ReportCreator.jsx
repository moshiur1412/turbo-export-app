import { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';
import Select from 'react-select';
import DateRangePicker from './DateRangePicker';
import SearchableReportType from './SearchableReportType';

const REPORT_FILTER_CONFIG = {
    salary_master: { date_range: true, departments: true, designations: true, employees: true, locations: true, employment_status: true, gender: true, salary_range: true, include_inactive: true },
    salary_by_department: { date_range: true, departments: true, designations: true, locations: true, employment_status: true },
    salary_by_designation: { date_range: true, departments: true, designations: true, locations: true, employment_status: true },
    salary_by_location: { date_range: true, departments: true, locations: true, employment_status: true },
    salary_monthly_comparative: { date_range: true, departments: true, locations: true, employment_status: true },
    salary_bank_advice: { date_range: true, departments: true, employees: true, locations: true, employment_status: true },
    attendance_daily: { date_range: false, departments: true, single_date: true, employees: true, designations: true, locations: true, employment_status: true, include_inactive: true },
    attendance_monthly: { date_range: true, departments: true, employees: true, designations: true, locations: true, employment_status: true, include_inactive: true },
    attendance_late_trends: { date_range: true, departments: true, designations: true, locations: true, employment_status: true },
    attendance_overtime: { date_range: true, departments: true, designations: true, locations: true, employment_status: true },
    leave_balance: { date_range: false, departments: true, year_only: true, designations: true, locations: true, employment_status: true },
    leave_encashment: { date_range: true, departments: true, designations: true, locations: true, employment_status: true },
    leave_department_heatmap: { date_range: false, departments: true, year_only: true, designations: true, locations: true, employment_status: true },
    leave_availed: { date_range: true, departments: true, designations: true, locations: true, employment_status: true, leave_type: true },
    employee_recruitment: { date_range: true, departments: true, designations: true, locations: true, employment_status: true, gender: true },
    employee_attrition: { date_range: true, departments: true, designations: true, locations: true, employment_status: true, gender: true },
    employee_service_length: { date_range: false, departments: true, designations: true, locations: true, employment_status: true },
    employee_profile_export: { date_range: false, departments: true, designations: true, locations: true, employment_status: true, gender: true, include_inactive: true },
};

const FORMAT_OPTIONS = [
    { value: 'csv', label: 'CSV' },
    { value: 'xlsx', label: 'Excel (XLSX)' },
    { value: 'pdf', label: 'PDF' },
    { value: 'docx', label: 'Word (DOCX)' },
    { value: 'sql', label: 'SQL' },
];

const YEAR_OPTIONS = Array.from({ length: 10 }, (_, i) => {
    const year = new Date().getFullYear() - i;
    return { value: year, label: year.toString() };
});

const LOCATION_OPTIONS = [
    { value: 'head_office', label: 'Head Office' },
    { value: 'branch_1', label: 'Branch 1' },
    { value: 'branch_2', label: 'Branch 2' },
    { value: 'remote', label: 'Remote' },
];

const EMPLOYMENT_STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'probation', label: 'Probation' },
    { value: 'contract', label: 'Contract' },
    { value: 'part_time', label: 'Part Time' },
    { value: 'intern', label: 'Intern' },
    { value: 'resigned', label: 'Resigned' },
    { value: 'terminated', label: 'Terminated' },
];

const GENDER_OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'other', label: 'Other' },
];

const LEAVE_TYPE_OPTIONS = [
    { value: 'sick', label: 'Sick Leave' },
    { value: 'casual', label: 'Casual Leave' },
    { value: 'earned', label: 'Earned Leave' },
    { value: 'maternity', label: 'Maternity Leave' },
    { value: 'paternity', label: 'Paternity Leave' },
    { value: 'unpaid', label: 'Unpaid Leave' },
];

const getDisplayValue = (key, value, departments, designations, employees, locations) => {
    if (value === null || value === undefined) return '-';

    const keyLower = key.toLowerCase();
    const stringValue = String(value).trim();

    if (keyLower.includes('designation') && !keyLower.includes('_name') && !keyLower.includes('name_')) {
        const numValue = typeof value === 'number' ? value : parseInt(stringValue, 10);
        if (!isNaN(numValue)) {
            const found = designations.find(d => d.value === numValue);
            if (found) return found.label;
        }
        const foundByLabel = designations.find(d => d.label.toLowerCase() === stringValue.toLowerCase());
        if (foundByLabel) return foundByLabel.label;
        return stringValue || '-';
    }

    if (keyLower.includes('department') && !keyLower.includes('_name') && !keyLower.includes('name_')) {
        const numValue = typeof value === 'number' ? value : parseInt(stringValue, 10);
        if (!isNaN(numValue)) {
            const found = departments.find(d => d.value === numValue);
            if (found) return found.label;
        }
        const foundByLabel = departments.find(d => d.label.toLowerCase() === stringValue.toLowerCase());
        if (foundByLabel) return foundByLabel.label;
        return stringValue || '-';
    }

    if (keyLower.includes('employee') && !keyLower.includes('_name') && !keyLower.includes('name_') && !keyLower.includes('_id')) {
        const numValue = typeof value === 'number' ? value : parseInt(stringValue, 10);
        if (!isNaN(numValue)) {
            const found = employees.find(e => e.value === numValue);
            if (found) return found.label;
        }
        const foundByLabel = employees.find(e => e.label.toLowerCase().includes(stringValue.toLowerCase()));
        if (foundByLabel) return foundByLabel.label;
        return stringValue || '-';
    }

    if (keyLower.includes('location') && !keyLower.includes('_name') && !keyLower.includes('name_')) {
        const foundByValue = locations.find(l => l.value === stringValue);
        if (foundByValue) return foundByValue.label;
        const foundByLabel = locations.find(l => l.label.toLowerCase() === stringValue.toLowerCase());
        if (foundByLabel) return foundByLabel.label;
        return stringValue || '-';
    }

    if (keyLower.includes('status') || keyLower.includes('employment_status')) {
        const found = EMPLOYMENT_STATUS_OPTIONS.find(s =>
            s.value.toLowerCase() === stringValue.toLowerCase() ||
            s.label.toLowerCase() === stringValue.toLowerCase()
        );
        if (found) return found.label;
        return stringValue || '-';
    }

    if (keyLower.includes('gender')) {
        const found = GENDER_OPTIONS.find(g =>
            g.value.toLowerCase() === stringValue.toLowerCase() ||
            g.label.toLowerCase() === stringValue.toLowerCase()
        );
        if (found) return found.label;
        return stringValue || '-';
    }

    if (keyLower.includes('leave_type') || keyLower.includes('leavetype')) {
        const found = LEAVE_TYPE_OPTIONS.find(l =>
            l.value.toLowerCase() === stringValue.toLowerCase() ||
            l.label.toLowerCase() === stringValue.toLowerCase()
        );
        if (found) return found.label;
        return stringValue || '-';
    }

    if (keyLower === 'id' || keyLower.endsWith('_id')) {
        return stringValue || '-';
    }

    if (keyLower.includes('date') || keyLower.includes('_at')) {
        return formatDate(value);
    }

    return stringValue || '-';
};

const toTitleCase = (str) => {
    if (!str) return '';
    const labels = {
        'id': 'ID',
        'employee_id': 'Employee',
        'designation_id': 'Designation',
        'department_id': 'Department',
        'user_id': 'User',
        'location_id': 'Location',
        'salary_id': 'Salary',
        'employment_status': 'Employment Status',
        'created_at': 'Created',
        'updated_at': 'Updated',
        'email_verified_at': 'Email Verified',
        'join_date': 'Join Date',
        'start_date': 'Start Date',
        'end_date': 'End Date',
    };

    const lowerStr = str.toLowerCase();
    if (labels[lowerStr]) return labels[lowerStr];

    return str
        .replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase())
        .replace(/Id\b/g, 'ID')
        .replace(/Email\b/g, 'Email');
};

const formatDate = (date) => {
    if (!date) return '-';
    try {
        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const day = d.getDate().toString().padStart(2, '0');
        const month = months[d.getMonth()];
        const year = d.getFullYear().toString().slice(-2);
        return `${day}-${month}-${year}`;
    } catch (e) {
        return '-';
    }
};

const selectStyles = {
    control: (provided) => ({
        ...provided,
        borderRadius: '8px',
        borderColor: '#E5E7EB',
        minHeight: '40px',
        fontSize: '13px',
        padding: '2px',
    }),
    input: (provided) => ({
        ...provided,
        fontSize: '13px',
    }),
    menu: (provided) => ({
        ...provided,
        fontSize: '13px',
        zIndex: 50,
    }),
    singleValue: (provided) => ({
        ...provided,
        fontSize: '13px',
    }),
    placeholder: (provided) => ({
        ...provided,
        fontSize: '13px',
    }),
};

export default function ReportCreator({ onReportCreated }) {
    const [reportType, setReportType] = useState(null);
    const [formats] = useState(FORMAT_OPTIONS);
    const [departments, setDepartments] = useState([]);
    const [designations, setDesignations] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [selectedDepartmentOptions, setSelectedDepartmentOptions] = useState([]);
    const [selectedDesignationOptions, setSelectedDesignationOptions] = useState([]);
    const [selectedEmployeeOptions, setSelectedEmployeeOptions] = useState([]);
    const [locations] = useState(LOCATION_OPTIONS);
    const [loadingFilters, setLoadingFilters] = useState({ departments: false, designations: false, employees: false });

    const [departmentPage, setDepartmentPage] = useState(1);
    const [departmentHasMore, setDepartmentHasMore] = useState(true);
    const [departmentSearch, setDepartmentSearch] = useState('');
    const departmentSearchTimeout = useRef(null);

    const [designationPage, setDesignationPage] = useState(1);
    const [designationHasMore, setDesignationHasMore] = useState(true);
    const [designationSearch, setDesignationSearch] = useState('');
    const designationSearchTimeout = useRef(null);

    const [employeePage, setEmployeePage] = useState(1);
    const [employeeHasMore, setEmployeeHasMore] = useState(true);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const employeeSearchTimeout = useRef(null);
    const employeeListRef = useRef(null);
    const [submitting, setSubmitting] = useState(false);
    const [previewing, setPreviewing] = useState(false);
    const [previewData, setPreviewData] = useState(null);
    const [previewTotal, setPreviewTotal] = useState(0);
    const [showPreview, setShowPreview] = useState(false);
    const [error, setError] = useState(null);
    const [formattedFilters, setFormattedFilters] = useState({});
    const autoPreviewTimeout = useRef(null);

    const [formData, setFormData] = useState({
        format: 'csv',
        customName: '',
        startDate: '',
        endDate: '',
        singleDate: new Date().toISOString().split('T')[0],
        year: new Date().getFullYear(),
        departmentIds: [],
        designationIds: [],
        employeeIds: [],
        employeeSearch: '',
        locationIds: [],
        employmentStatus: [],
        gender: [],
        salaryMin: '',
        salaryMax: '',
        includeInactive: false,
        leaveTypeIds: [],
    });

    const loadDepartments = useCallback(async (search = '', page = 1, append = false) => {
        if (page === 1) {
            setLoadingFilters(prev => ({ ...prev, departments: true }));
        }
        try {
            const response = await axios.get('/api/departments', { params: { search, page, per_page: 50 } });
            let options = [];
            if (response.data.data) {
                options = response.data.data.map(d => ({
                    value: d.id,
                    label: d.name,
                }));
            }
            if (append) {
                setDepartments(prev => [...prev, ...options]);
            } else {
                setDepartments(options);
            }
            setDepartmentHasMore(response.data.has_more);
            setDepartmentPage(page);
            return options;
        } catch (err) {
            console.error('Failed to fetch departments:', err);
            if (!append) setDepartments([]);
            return [];
        } finally {
            if (page === 1) {
                setLoadingFilters(prev => ({ ...prev, departments: false }));
            }
        }
    }, []);

    const loadMoreDepartments = useCallback(async () => {
        if (!departmentHasMore || loadingFilters.departments) return;
        const nextPage = departmentPage + 1;
        await loadDepartments(departmentSearch, nextPage, true);
    }, [departmentHasMore, departmentPage, departmentSearch, loadingFilters.departments, loadDepartments]);

    const departmentSearchRef = useRef('');
    const handleDepartmentSearch = useCallback((input) => {
        departmentSearchRef.current = input;
        if (departmentSearchTimeout.current) {
            clearTimeout(departmentSearchTimeout.current);
        }
        if (input.length === 0) {
            loadDepartments('', 1, false);
            return;
        }
        departmentSearchTimeout.current = setTimeout(() => {
            if (departmentSearchRef.current === input) {
                loadDepartments(input, 1, false);
            }
        }, 150);
    }, [loadDepartments]);

    const loadDesignations = useCallback(async (search = '', page = 1, append = false) => {
        if (page === 1) {
            setLoadingFilters(prev => ({ ...prev, designations: true }));
        }
        try {
            const response = await axios.get('/api/designations', { params: { search, page, per_page: 50 } });
            let options = [];
            if (response.data.data) {
                options = response.data.data.map(d => ({
                    value: d.id,
                    label: d.name,
                }));
            }
            if (append) {
                setDesignations(prev => [...prev, ...options]);
            } else {
                setDesignations(options);
            }
            setDesignationHasMore(response.data.has_more);
            setDesignationPage(page);
            return options;
        } catch (err) {
            console.error('Failed to fetch designations:', err);
            if (!append) setDesignations([]);
            return [];
        } finally {
            if (page === 1) {
                setLoadingFilters(prev => ({ ...prev, designations: false }));
            }
        }
    }, [designations, formData.designationIds]);

    const loadMoreDesignations = useCallback(async () => {
        if (!designationHasMore || loadingFilters.designations) return;
        const nextPage = designationPage + 1;
        await loadDesignations(designationSearch, nextPage, true);
    }, [designationHasMore, designationPage, designationSearch, loadingFilters.designations, loadDesignations]);

    const designationSearchRef = useRef('');
    const handleDesignationSearch = useCallback((input) => {
        designationSearchRef.current = input;
        if (designationSearchTimeout.current) {
            clearTimeout(designationSearchTimeout.current);
        }
        if (input.length === 0) {
            loadDesignations('', 1, false);
            return;
        }
        designationSearchTimeout.current = setTimeout(() => {
            if (designationSearchRef.current === input) {
                loadDesignations(input, 1, false);
            }
        }, 150);
    }, [loadDesignations]);

    const loadEmployees = useCallback(async (search = '', page = 1, append = false) => {
        if (page === 1) {
            setLoadingFilters(prev => ({ ...prev, employees: true }));
        }
        try {
            const response = await axios.get('/api/employees', { params: { search, page, per_page: 50 } });
            let options = [];
            if (response.data.data) {
                options = response.data.data.map(e => ({
                    value: e.id,
                    label: e.gender ? `${e.name} (${e.employee_id || e.id}) - ${e.gender}` : `${e.name} (${e.employee_id || e.id})`,
                }));
            }
            if (append) {
                setEmployees(prev => [...prev, ...options]);
            } else {
                setEmployees(options);
            }
            setEmployeeHasMore(response.data.has_more);
            setEmployeePage(page);
            return options;
        } catch (err) {
            console.error('Failed to fetch employees:', err);
            if (!append) setEmployees([]);
            return [];
        } finally {
            if (page === 1) {
                setLoadingFilters(prev => ({ ...prev, employees: false }));
            }
        }
    }, []);

    const loadMoreEmployees = useCallback(async () => {
        if (!employeeHasMore || loadingFilters.employees) return;
        const nextPage = employeePage + 1;
        await loadEmployees(employeeSearch, nextPage, true);
    }, [employeeHasMore, employeePage, employeeSearch, loadingFilters.employees, loadEmployees]);

    const handleEmployeeSearch = useCallback((input) => {
        if (employeeSearchTimeout.current) {
            clearTimeout(employeeSearchTimeout.current);
        }
        if (input.length === 0) {
            loadEmployees('', 1, false);
            return;
        }
        employeeSearchTimeout.current = setTimeout(() => {
            loadEmployees(input, 1, false);
        }, 300);
    }, [loadEmployees]);



    const getFilterConfig = (type) => {
        if (!type) return null;
        return REPORT_FILTER_CONFIG[type.value] || null;
    };

    const filterConfig = getFilterConfig(reportType);

    const handleReportTypeChange = (type) => {
        setReportType(type);
        setFormData(prev => ({
            ...prev,
            customName: '',
            startDate: '',
            endDate: '',
            singleDate: new Date().toISOString().split('T')[0],
            year: new Date().getFullYear(),
            departmentIds: [],
            designationIds: [],
            employeeIds: [],
            locationIds: [],
            employmentStatus: [],
            gender: [],
            salaryMin: '',
            salaryMax: '',
            includeInactive: false,
            leaveTypeIds: [],
        }));
        setSelectedDepartmentOptions([]);
        setSelectedDesignationOptions([]);
        setSelectedEmployeeOptions([]);
        setShowPreview(false);
        setPreviewData(null);
        if (type) {
            fetchPreview();
        }
    };

    const handleFilterChange = (field, value) => {
        const newFormData = { ...formData, [field]: value };
        setFormData(newFormData);

        if (autoPreviewTimeout.current) {
            clearTimeout(autoPreviewTimeout.current);
        }
        autoPreviewTimeout.current = setTimeout(() => {
            fetchPreviewWithData(newFormData);
        }, 800);
    };

    const buildFiltersWithData = (data) => {
        const filters = {};

        if (filterConfig?.date_range) {
            filters.start_date = data.startDate;
            filters.end_date = data.endDate;
        }

        if (filterConfig?.single_date) {
            filters.date = data.singleDate;
        }

        if (filterConfig?.year_only) {
            filters.year = data.year;
        }

        if (data.departmentIds && data.departmentIds.length > 0) {
            filters.department_ids = data.departmentIds;
        }

        if (data.designationIds && data.designationIds.length > 0) {
            filters.designation_ids = data.designationIds;
        }

        if (data.employeeIds && data.employeeIds.length > 0) {
            filters.user_ids = data.employeeIds;
        }

        if (data.locationIds && data.locationIds.length > 0) {
            filters.location_ids = data.locationIds;
        }

        if (data.employmentStatus && data.employmentStatus.length > 0) {
            filters.employment_status = data.employmentStatus;
        }

        if (data.gender && data.gender.length > 0) {
            filters.gender = data.gender;
        }

        if (data.salaryMin) {
            filters.salary_min = data.salaryMin;
        }

        if (data.salaryMax) {
            filters.salary_max = data.salaryMax;
        }

        if (data.includeInactive) {
            filters.include_inactive = data.includeInactive;
        }

        if (data.leaveTypeIds && data.leaveTypeIds.length > 0) {
            filters.leave_type_ids = data.leaveTypeIds;
        }

        return filters;
    };

    const fetchPreviewWithData = async (data) => {
        if (!reportType) return;

        if (filterConfig?.date_range && (!data.startDate || !data.endDate)) {
            return;
        }

        setPreviewing(true);
        setError(null);

        try {
            const filters = buildFiltersWithData(data);
            const response = await axios.post('/api/reports/preview', {
                type: reportType.value,
                filters,
            });

            if (response.data.success) {
                setPreviewData(response.data.data);
                setPreviewTotal(response.data.total_count);
                setFormattedFilters(response.data.filters || {});
                setShowPreview(true);
            } else {
                setError(response.data.error || 'Preview failed');
            }
        } catch (err) {
            console.error('Preview failed:', err);
            setError(err.response?.data?.error || 'Preview failed. Please try again.');
        } finally {
            setPreviewing(false);
        }
    };

    const fetchPreview = async () => {
        fetchPreviewWithData(formData);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError(null);

        if (!reportType) {
            setError('Please select a report type');
            return;
        }

        if (filterConfig?.date_range && (!formData.startDate || !formData.endDate)) {
            setError('This report requires a date range');
            return;
        }

        setSubmitting(true);

        try {
            const response = await axios.post('/api/reports', {
                type: reportType.value,
                format: formData.format,
                name: formData.customName || undefined,
                filters: buildFiltersWithData(formData),
            });

            if (response.data.success) {
                setReportType(null);
                setFormData({
                    format: 'csv',
                    customName: '',
                    startDate: '',
                    endDate: '',
                    singleDate: new Date().toISOString().split('T')[0],
                    year: new Date().getFullYear(),
                    departmentIds: [],
                    designationIds: [],
                    employeeIds: [],
                    locationIds: [],
                    employmentStatus: [],
                    gender: [],
                    salaryMin: '',
                    salaryMax: '',
                    includeInactive: false,
                    leaveTypeIds: [],
                });
                setShowPreview(false);
                setPreviewData(null);
                onReportCreated?.(response.data.data);
            } else {
                setError(response.data.error || 'Failed to create report');
            }
        } catch (err) {
            console.error('Failed to create report:', err);
            setError(err.response?.data?.error || 'Network error. Please check your connection.');
        } finally {
            setSubmitting(false);
        }
    };

    useEffect(() => {
        return () => {
            if (autoPreviewTimeout.current) {
                clearTimeout(autoPreviewTimeout.current);
            }
        };
    }, []);

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {error && (
                <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                            <svg className="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span className="text-red-700 text-sm">{error}</span>
                    </div>
                    <button type="button" onClick={() => setError(null)} className="text-red-400 hover:text-red-600">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            )}

            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div className="flex items-center gap-2 mb-4">
                    <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center">
                        <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Create New Report</h2>
                        <p className="text-xs text-gray-500">Select report type and configure options</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div className="lg:col-span-1">
                        <label className="block text-xs font-medium text-gray-600 mb-2">Report Type</label>
                        <SearchableReportType
                            value={reportType}
                            onChange={handleReportTypeChange}
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-2">Format</label>
                        <Select
                            options={formats}
                            value={formats.find(f => f.value === formData.format)}
                            onChange={(opt) => handleFilterChange('format', opt.value)}
                            styles={selectStyles}
                        />
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-2">Report Name (Optional)</label>
                        <input
                            type="text"
                            value={formData.customName}
                            onChange={(e) => handleFilterChange('customName', e.target.value)}
                            placeholder={reportType?.label || 'Auto-generated'}
                            className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                    </div>
                

                <div className="mt-6 flex justify-end">
                    <button
                        type="submit"
                        disabled={submitting || !reportType}
                        className="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-medium rounded-xl hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-blue-500/30 flex items-center gap-2"
                    >
                        {submitting ? (
                            <>
                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Creating Report...
                            </>
                        ) : (
                            <>
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Generate Report
                            </>
                        )}
                    </button>
                </div>
                </div>
            </div>

            {reportType && (
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-100">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-gray-900">Filters</h3>
                                    <p className="text-xs text-gray-500">{reportType.label}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {previewing && (
                                    <span className="flex items-center gap-1.5 text-xs text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                                        <svg className="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Updating...
                                    </span>
                                )}
                                <span className="text-xs text-gray-400">Auto-updates preview</span>
                            </div>
                        </div>
                    </div>

                    <div className="p-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {filterConfig?.date_range && (
                                <div className="md:col-span-1">
                                    <DateRangePicker
                                        label="Date Range"
                                        startDate={formData.startDate}
                                        endDate={formData.endDate}
                                        onChange={({ startDate, endDate }) => {
                                            const newFormData = { ...formData, startDate, endDate };
                                            setFormData(newFormData);
                                            if (startDate && endDate) {
                                                if (autoPreviewTimeout.current) {
                                                    clearTimeout(autoPreviewTimeout.current);
                                                }
                                                autoPreviewTimeout.current = setTimeout(() => {
                                                    fetchPreviewWithData(newFormData);
                                                }, 800);
                                            }
                                        }}
                                    />
                                </div>
                            )}

                            {filterConfig?.single_date && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Date</label>
                                    <input
                                        type="date"
                                        value={formData.singleDate}
                                        onChange={(e) => handleFilterChange('singleDate', e.target.value)}
                                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    />
                                </div>
                            )}

                            {filterConfig?.year_only && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Year</label>
                                    <Select
                                        options={YEAR_OPTIONS}
                                        value={YEAR_OPTIONS.find(y => y.value === formData.year)}
                                        onChange={(opt) => handleFilterChange('year', opt.value)}
                                        styles={selectStyles}
                                    />
                                </div>
                            )}

                            {filterConfig?.departments && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Departments</label>
                                    <Select
                                        isMulti
                                        options={departments}
                                        value={selectedDepartmentOptions}
                                        onChange={(selected) => {
                                            setSelectedDepartmentOptions(selected || []);
                                            handleFilterChange('departmentIds', selected ? selected.map(s => s.value) : []);
                                        }}
                                        placeholder={loadingFilters.departments ? "Loading..." : selectedDepartmentOptions.length > 0 ? `${selectedDepartmentOptions.length} selected` : departments.length > 0 ? `${departments.length} departments` : "Search departments..."}
                                        styles={selectStyles}
                                        isSearchable
                                        isLoading={loadingFilters.departments}
                                        onInputChange={(input) => {
                                            handleDepartmentSearch(input);
                                            return input;
                                        }}
                                        onFocus={() => {
                                            if (departments.length === 0) {
                                                loadDepartments('', 1, false);
                                            }
                                        }}
                                        onMenuScrollToBottom={loadMoreDepartments}
                                        closeMenuOnSelect={false}
                                        hideSelectedOptions={false}
                                        backspaceRemovesValue={true}
                                    />
                                </div>
                            )}

                            {filterConfig?.designations && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Designations</label>
                                    <Select
                                        isMulti
                                        options={designations}
                                        value={selectedDesignationOptions}
                                        onChange={(selected) => {
                                            setSelectedDesignationOptions(selected || []);
                                            handleFilterChange('designationIds', selected ? selected.map(s => s.value) : []);
                                        }}
                                        placeholder={loadingFilters.designations ? "Loading..." : selectedDesignationOptions.length > 0 ? `${selectedDesignationOptions.length} selected` : designations.length > 0 ? `${designations.length} designations` : "Search designations..."}
                                        styles={selectStyles}
                                        isSearchable
                                        isLoading={loadingFilters.designations}
                                        onInputChange={(input) => {
                                            handleDesignationSearch(input);
                                            return input;
                                        }}
                                        onFocus={() => {
                                            if (designations.length === 0) {
                                                loadDesignations('', 1, false);
                                            }
                                        }}
                                        onMenuScrollToBottom={loadMoreDesignations}
                                        closeMenuOnSelect={false}
                                        hideSelectedOptions={false}
                                        backspaceRemovesValue={true}
                                    />
                                </div>
                            )}

                            {filterConfig?.employees && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Employees</label>
                                    <Select
                                        ref={employeeListRef}
                                        isMulti
                                        options={employees}
                                        value={selectedEmployeeOptions}
                                        onChange={(selected) => {
                                            setSelectedEmployeeOptions(selected || []);
                                            handleFilterChange('employeeIds', selected ? selected.map(s => s.value) : []);
                                        }}
                                        placeholder={loadingFilters.employees ? "Searching..." : selectedEmployeeOptions.length > 0 ? `${selectedEmployeeOptions.length} selected` : employees.length > 0 ? `${employees.length} employees` : "Search employees..."}
                                        styles={selectStyles}
                                        isSearchable
                                        isLoading={loadingFilters.employees}
                                        onInputChange={(input) => {
                                            handleEmployeeSearch(input);
                                        }}
                                        onFocus={() => {
                                            loadEmployees('', 1, false);
                                        }}
                                        onMenuScrollToBottom={loadMoreEmployees}
                                        closeMenuOnSelect={false}
                                        hideSelectedOptions={false}
                                        backspaceRemovesValue={true}
                                        openMenuOnFocus={true}
                                        blurInputOnSelect={false}
                                    />
                                </div>
                            )}

                            {filterConfig?.locations && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Locations</label>
                                    <Select
                                        isMulti
                                        options={locations}
                                        value={locations.filter(l => formData.locationIds.includes(l.value))}
                                        onChange={(selected) => handleFilterChange('locationIds', selected.map(s => s.value))}
                                        placeholder="All Locations"
                                        styles={selectStyles}
                                        isSearchable
                                    />
                                </div>
                            )}

                            {filterConfig?.employment_status && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Employment Status</label>
                                    <Select
                                        isMulti
                                        options={EMPLOYMENT_STATUS_OPTIONS}
                                        value={EMPLOYMENT_STATUS_OPTIONS.filter(s => formData.employmentStatus.includes(s.value))}
                                        onChange={(selected) => handleFilterChange('employmentStatus', selected.map(s => s.value))}
                                        placeholder="All Statuses"
                                        styles={selectStyles}
                                        isSearchable
                                    />
                                </div>
                            )}

                            {filterConfig?.gender && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Gender</label>
                                    <Select
                                        isMulti
                                        options={GENDER_OPTIONS}
                                        value={GENDER_OPTIONS.filter(g => formData.gender.includes(g.value))}
                                        onChange={(selected) => handleFilterChange('gender', selected.map(s => s.value))}
                                        placeholder="All Genders"
                                        styles={selectStyles}
                                    />
                                </div>
                            )}

                            {filterConfig?.leave_type && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-600 mb-2">Leave Type</label>
                                    <Select
                                        isMulti
                                        options={LEAVE_TYPE_OPTIONS}
                                        value={LEAVE_TYPE_OPTIONS.filter(l => formData.leaveTypeIds.includes(l.value))}
                                        onChange={(selected) => handleFilterChange('leaveTypeIds', selected.map(s => s.value))}
                                        placeholder="All Leave Types"
                                        styles={selectStyles}
                                        isSearchable
                                    />
                                </div>
                            )}

                            {filterConfig?.salary_range && (
                                <>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-2">Min Salary</label>
                                        <input
                                            type="number"
                                            value={formData.salaryMin}
                                            onChange={(e) => handleFilterChange('salaryMin', e.target.value)}
                                            placeholder="0"
                                            className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-2">Max Salary</label>
                                        <input
                                            type="number"
                                            value={formData.salaryMax}
                                            onChange={(e) => handleFilterChange('salaryMax', e.target.value)}
                                            placeholder="999999"
                                            className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                        />
                                    </div>
                                </>
                            )}

                            {filterConfig?.include_inactive && (
                                <div className="flex items-end pb-2">
                                    <label className="flex items-center gap-3 cursor-pointer group">
                                        <div className="relative">
                                            <input
                                                type="checkbox"
                                                checked={formData.includeInactive}
                                                onChange={(e) => handleFilterChange('includeInactive', e.target.checked)}
                                                className="sr-only"
                                            />
                                            <div className={`w-11 h-6 rounded-full transition-colors ${formData.includeInactive ? 'bg-blue-600' : 'bg-gray-200'}`}>
                                                <div className={`w-5 h-5 bg-white rounded-full shadow-md transform transition-transform mt-0.5 ${formData.includeInactive ? 'translate-x-5 ml-0.5' : 'translate-x-0.5'}`}></div>
                                            </div>
                                        </div>
                                        <span className="text-sm text-gray-700 group-hover:text-gray-900">Include Inactive</span>
                                    </label>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {showPreview && previewData && (
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="bg-gradient-to-r from-green-50 to-green-100 px-6 py-4 border-b border-green-100">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center">
                                    <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-green-800">Data Preview</h3>
                                    <p className="text-xs text-green-600">{previewTotal.toLocaleString()} total records found</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowPreview(false)}
                                className="text-green-600 hover:text-green-800 p-1 rounded-lg hover:bg-green-200 transition-colors"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {Object.keys(formattedFilters).length > 0 && (
                        <div className="px-6 py-3 bg-gray-50 border-b border-gray-100">
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <span className="font-bold text-gray-700">Filters:</span>
                                {formattedFilters.start_date && formattedFilters.end_date && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Date Range:</span>
                                        {formattedFilters.start_date} To {formattedFilters.end_date}
                                    </span>
                                )}
                                {formattedFilters.date && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Date:</span>
                                        {formattedFilters.date}
                                    </span>
                                )}
                                {formattedFilters.year && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Year:</span>
                                        {formattedFilters.year}
                                    </span>
                                )}
                                {formattedFilters.department && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Department:</span>
                                        {formattedFilters.department}
                                    </span>
                                )}
                                {formattedFilters.designation && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Designation:</span>
                                        {formattedFilters.designation}
                                    </span>
                                )}
                                {formattedFilters.employee && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Employee:</span>
                                        {formattedFilters.employee}
                                    </span>
                                )}
                                {formattedFilters.location && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Location:</span>
                                        {formattedFilters.location}
                                    </span>
                                )}
                                {formattedFilters.employment_status && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Status:</span>
                                        {formattedFilters.employment_status}
                                    </span>
                                )}
                                {formattedFilters.gender && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Gender:</span>
                                        {formattedFilters.gender}
                                    </span>
                                )}
                                {formattedFilters.salary_min && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Min Salary:</span>
                                        {formattedFilters.salary_min}
                                    </span>
                                )}
                                {formattedFilters.salary_max && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Max Salary:</span>
                                        {formattedFilters.salary_max}
                                    </span>
                                )}
                                {formattedFilters.leave_type && (
                                    <span className="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                        <span className="font-bold">Leave Type:</span>
                                        {formattedFilters.leave_type}
                                    </span>
                                )}
                            </div>
                        </div>
                    )}

                    <div className="overflow-x-auto max-h-96">
                        <table className="w-full">
                            <thead className="bg-gray-50 sticky top-0">
                                <tr>
                                    {previewData.length > 0 && Object.keys(previewData[0]).map((key) => (
                                        <th key={key} className="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap">
                                            {toTitleCase(key)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {previewData.map((row, idx) => (
                                    <tr key={idx} className="hover:bg-blue-50/50 transition-colors">
                                        {Object.entries(row).map(([key, val], i) => (
                                            <td key={i} className="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                                {getDisplayValue(key, val, departments, designations, employees, locations)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </form>
    );
}
