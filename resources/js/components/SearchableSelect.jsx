import { useState, useEffect, useRef, useCallback } from 'react';
import Select from 'react-select';

const customStyles = {
    control: (provided, state) => ({
        ...provided,
        borderRadius: '0.5rem',
        borderColor: state.isFocused ? '#3B82F6' : '#D1D5DB',
        boxShadow: state.isFocused ? '0 0 0 3px rgba(59, 130, 246, 0.1)' : 'none',
        '&:hover': {
            borderColor: state.isFocused ? '#3B82F6' : '#9CA3AF',
        },
    }),
    input: (provided) => ({
        ...provided,
        fontSize: '0.875rem',
    }),
    menu: (provided) => ({
        ...provided,
        borderRadius: '0.5rem',
        boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        zIndex: 50,
    }),
    option: (provided, state) => ({
        ...provided,
        fontSize: '0.875rem',
        backgroundColor: state.isSelected ? '#EFF6FF' : state.isFocused ? '#F9FAFB' : 'white',
        color: state.isSelected ? '#1D4ED8' : '#374151',
        '&:hover': {
            backgroundColor: '#F3F4F6',
        },
    }),
};

export default function SearchableSelect({
    options = [],
    value = [],
    onChange,
    placeholder = 'Search and select...',
    label,
    loading = false,
    searchable = true,
    multiple = true,
    clearable = true,
    grouped = false,
}) {
    return (
        <div className="w-full">
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-2">
                    {label}
                </label>
            )}
            <Select
                isMulti={multiple}
                options={options}
                value={value}
                onChange={onChange}
                isLoading={loading}
                isSearchable={searchable}
                isClearable={clearable}
                styles={customStyles}
                placeholder={placeholder}
                classNamePrefix="select"
                closeMenuOnSelect={!multiple}
                hideSelectedOptions={false}
                theme={(theme) => ({
                    ...theme,
                    borderRadius: 8,
                    colors: {
                        ...theme.colors,
                        primary: '#3B82F6',
                        primary25: '#EFF6FF',
                        primary50: '#DBEAFE',
                    },
                })}
            />
        </div>
    );
}

export function useSearchableOptions(fetchFn, { pageSize = 20 } = {}) {
    const [options, setOptions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [hasMore, setHasMore] = useState(true);
    const [page, setPage] = useState(1);
    const observerRef = useRef();
    const loadingRef = useRef(false);

    const loadOptions = useCallback(async (reset = false) => {
        if (loadingRef.current) return;
        loadingRef.current = true;
        setLoading(true);

        try {
            const currentPage = reset ? 1 : page;
            const result = await fetchFn({ search, page: currentPage, pageSize });

            if (reset) {
                setOptions(result.options);
            } else {
                setOptions(prev => [...prev, ...result.options]);
            }

            setHasMore(result.hasMore);
            setPage(currentPage + 1);
        } catch (error) {
            console.error('Failed to load options:', error);
        } finally {
            setLoading(false);
            loadingRef.current = false;
        }
    }, [fetchFn, search, page, pageSize]);

    useEffect(() => {
        loadOptions(true);
    }, [search]);

    const searchHandler = useCallback((query) => {
        setSearch(query);
        setPage(1);
        setHasMore(true);
    }, []);

    const loadMore = useCallback(() => {
        if (hasMore && !loading) {
            loadOptions(false);
        }
    }, [hasMore, loading, loadOptions]);

    const lastOptionRef = useCallback((node) => {
        if (loading) return;
        if (observerRef.current) observerRef.current.disconnect();
        
        observerRef.current = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && hasMore) {
                loadMore();
            }
        });

        if (node) observerRef.current.observe(node);
    }, [loading, hasMore, loadMore]);

    return {
        options,
        loading,
        hasMore,
        search,
        searchHandler,
        lastOptionRef,
        setOptions,
    };
}
