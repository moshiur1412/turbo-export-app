import { useState, useEffect, useCallback, useRef } from 'react';
import axios from 'axios';

const CATEGORY_ICONS = {
    'Salary & Financial': { icon: '💰', color: 'from-blue-500 to-blue-600', bgColor: 'bg-blue-50', textColor: 'text-blue-600' },
    'Attendance': { icon: '📅', color: 'from-green-500 to-green-600', bgColor: 'bg-green-50', textColor: 'text-green-600' },
    'Leave Management': { icon: '🏖️', color: 'from-purple-500 to-purple-600', bgColor: 'bg-purple-50', textColor: 'text-purple-600' },
    'Employee Lifecycle': { icon: '👥', color: 'from-orange-500 to-orange-600', bgColor: 'bg-orange-50', textColor: 'text-orange-600' },
};

export default function SearchableReportType({ value, onChange }) {
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [categories, setCategories] = useState({});
    const [filteredCategories, setFilteredCategories] = useState({});
    const [showDropdown, setShowDropdown] = useState(false);
    const [selectedType, setSelectedType] = useState(null);
    const dropdownRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => {
        fetchReportTypes();
    }, []);

    useEffect(() => {
        if (search) {
            const filtered = {};
            Object.entries(categories).forEach(([category, types]) => {
                const filteredTypes = types.filter(type =>
                    type.label.toLowerCase().includes(search.toLowerCase())
                );
                if (filteredTypes.length > 0) {
                    filtered[category] = filteredTypes;
                }
            });
            setFilteredCategories(filtered);
        } else {
            setFilteredCategories(categories);
        }
    }, [search, categories]);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setShowDropdown(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const fetchReportTypes = async () => {
        try {
            const response = await axios.get('/api/reports/types');
            if (response.data.success) {
                setCategories(response.data.data);
                setFilteredCategories(response.data.data);
            }
        } catch (error) {
            console.error('Failed to fetch report types:', error);
        }
    };

    const handleSelect = (type) => {
        setSelectedType(type);
        setSearch('');
        setShowDropdown(false);
        onChange(type);
    };

    const handleClear = () => {
        setSelectedType(null);
        setSearch('');
        onChange(null);
        inputRef.current?.focus();
    };

    const totalReports = Object.values(filteredCategories).reduce((sum, types) => sum + types.length, 0);

    return (
        <div className="w-full" ref={dropdownRef}>
            <label className="block text-sm font-medium text-gray-700 mb-2">
                Report Name <span className="text-red-500">*</span>
            </label>
            
            {selectedType ? (
                <div className="relative">
                    <div className={`p-4 border-2 border-blue-200 rounded-xl bg-blue-50`}>
                        <div className="flex items-start justify-between">
                            <div className="flex items-start gap-3">
                                <div className={`w-10 h-10 rounded-lg flex items-center justify-center text-xl ${
                                    CATEGORY_ICONS[selectedType.category]?.bgColor || 'bg-gray-100'
                                }`}>
                                    {CATEGORY_ICONS[selectedType.category]?.icon || '📄'}
                                </div>
                                <div>
                                    <div className="font-medium text-gray-900">{selectedType.label}</div>
                                    <div className="text-xs text-gray-500 mt-0.5">
                                        {selectedType.category} • Type: {selectedType.value}
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={handleClear}
                                className="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-200"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            ) : (
                <div className="relative">
                    <div className="relative">
                        <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            ref={inputRef}
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onFocus={() => setShowDropdown(true)}
                            placeholder="Search report by name..."
                            className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        />
                        {loading && (
                            <div className="absolute right-3 top-1/2 -translate-y-1/2">
                                <svg className="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        )}
                    </div>

                    {showDropdown && (
                        <div className="absolute z-50 mt-2 w-full bg-white rounded-xl shadow-xl border border-gray-200 max-h-96 overflow-y-auto">
                            <div className="sticky top-0 bg-gray-50 px-4 py-2 border-b border-gray-200">
                                <div className="text-xs text-gray-500 font-medium">
                                    {totalReports} report{totalReports !== 1 ? 's' : ''} found
                                </div>
                            </div>

                            {Object.entries(filteredCategories).map(([category, types]) => (
                                <div key={category}>
                                    <div className={`px-4 py-2 text-xs font-semibold uppercase tracking-wider flex items-center gap-2 sticky top-10 bg-white ${
                                        CATEGORY_ICONS[category]?.bgColor || 'bg-gray-50'
                                    }`}>
                                        <span>{CATEGORY_ICONS[category]?.icon}</span>
                                        <span className={CATEGORY_ICONS[category]?.textColor || 'text-gray-600'}>
                                            {category}
                                        </span>
                                        <span className="text-gray-400">({types.length})</span>
                                    </div>
                                    <div className="py-1">
                                        {types.map((type) => (
                                            <button
                                                key={type.value}
                                                type="button"
                                                onClick={() => handleSelect(type)}
                                                className="w-full text-left px-4 py-2.5 hover:bg-gray-50 transition-colors flex items-center justify-between group"
                                            >
                                                <span className="text-sm text-gray-700 group-hover:text-gray-900">
                                                    {type.label}
                                                </span>
                                                <svg className="w-4 h-4 text-gray-300 group-hover:text-blue-500 opacity-0 group-hover:opacity-100 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                </svg>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}

                            {totalReports === 0 && (
                                <div className="px-4 py-8 text-center text-gray-500">
                                    <svg className="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p className="text-sm">No reports found</p>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
