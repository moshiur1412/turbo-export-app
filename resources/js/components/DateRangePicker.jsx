import { useState, useRef, useEffect } from 'react';
import { format, startOfWeek, endOfWeek, startOfMonth, endOfMonth, startOfYear, endOfYear, subYears, subMonths, subDays, eachDayOfInterval, isSameMonth, isWithinInterval, startOfDay, endOfDay } from 'date-fns';

const PRESETS = [
    { label: 'Today', getValue: () => { const today = new Date(); return { start: today, end: today }; } },
    { label: 'Yesterday', getValue: () => { const yesterday = subDays(new Date(), 1); return { start: yesterday, end: yesterday }; } },
    { label: 'This Week', getValue: () => { const today = new Date(); return { start: startOfWeek(today, { weekStartsOn: 1 }), end: endOfWeek(today, { weekStartsOn: 1 }) }; } },
    { label: 'Last Week', getValue: () => { const today = new Date(); const lastWeek = subDays(today, 7); return { start: startOfWeek(lastWeek, { weekStartsOn: 1 }), end: endOfWeek(lastWeek, { weekStartsOn: 1 }) }; } },
    { label: 'This Month', getValue: () => { const today = new Date(); return { start: startOfMonth(today), end: endOfMonth(today) }; } },
    { label: 'Last Month', getValue: () => { const lastMonth = subMonths(new Date(), 1); return { start: startOfMonth(lastMonth), end: endOfMonth(lastMonth) }; } },
    { label: 'Last 3 Months', getValue: () => { const lastMonth = subMonths(new Date(), 1); return { start: startOfMonth(subMonths(lastMonth, 2)), end: endOfMonth(lastMonth) }; } },
    { label: 'Last 6 Months', getValue: () => { const lastMonth = subMonths(new Date(), 1); return { start: startOfMonth(subMonths(lastMonth, 5)), end: endOfMonth(lastMonth) }; } },
    { label: 'This Year', getValue: () => { const today = new Date(); return { start: startOfYear(today), end: endOfYear(today) }; } },
    { label: 'Last Year', getValue: () => { const lastYear = subYears(new Date(), 1); return { start: startOfYear(lastYear), end: endOfYear(lastYear) }; } },
    { label: 'Last 2 Years', getValue: () => { const lastYear = subYears(new Date(), 1); return { start: startOfYear(subYears(lastYear, 1)), end: endOfYear(lastYear) }; } },
    { label: 'Last 3 Years', getValue: () => { const lastYear = subYears(new Date(), 1); return { start: startOfYear(subYears(lastYear, 2)), end: endOfYear(lastYear) }; } },
    { label: 'Last 5 Years', getValue: () => { const lastYear = subYears(new Date(), 1); return { start: startOfYear(subYears(lastYear, 4)), end: endOfYear(lastYear) }; } },
];

export default function DateRangePicker({ startDate, endDate, onChange, label }) {
    const [isOpen, setIsOpen] = useState(false);
    const [viewDate, setViewDate] = useState(new Date());
    const [selectingStart, setSelectingStart] = useState(true);
    const [tempRange, setTempRange] = useState({ start: null, end: null });
    const [searchQuery, setSearchQuery] = useState('');
    const dropdownRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const formatDisplayDate = (date) => {
        if (!date) return '';
        return format(new Date(date), 'd-MMM-yy');
    };

    const filteredPresets = PRESETS.filter(preset =>
        preset.label.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const displayValue = () => {
        if (startDate && endDate) {
            return `${formatDisplayDate(startDate)} - ${formatDisplayDate(endDate)}`;
        }
        if (startDate) return `From ${formatDisplayDate(startDate)}`;
        return 'Select date range';
    };

    const handlePresetClick = (preset) => {
        const { start, end } = preset.getValue();
        const formattedStart = format(start, 'yyyy-MM-dd');
        const formattedEnd = format(end, 'yyyy-MM-dd');
        onChange({ startDate: formattedStart, endDate: formattedEnd });
        setIsOpen(false);
    };

    const handleDayClick = (date) => {
        const dateStr = format(date, 'yyyy-MM-dd');
        
        if (selectingStart) {
            setTempRange({ start: date, end: null });
            setSelectingStart(false);
        } else {
            if (tempRange.start && date < tempRange.start) {
                setTempRange({ start: date, end: tempRange.start });
                setSelectingStart(true);
            } else {
                setTempRange({ ...tempRange, end: date });
                setSelectingStart(true);
            }
            
            if (tempRange.start) {
                const finalStart = date < tempRange.start ? date : tempRange.start;
                const finalEnd = date < tempRange.start ? tempRange.start : date;
                onChange({ 
                    startDate: format(finalStart, 'yyyy-MM-dd'), 
                    endDate: format(finalEnd, 'yyyy-MM-dd') 
                });
                setTempRange({ start: null, end: null });
            }
        }
    };

    const getDaysInMonth = () => {
        const start = startOfMonth(viewDate);
        const end = endOfMonth(viewDate);
        const days = eachDayOfInterval({ start, end });
        
        let startDay = start.getDay();
        const paddedDays = [];
        
        for (let i = 0; i < startDay; i++) {
            const prevDate = subDays(start, startDay - i);
            paddedDays.push({ date: prevDate, currentMonth: false });
        }
        
        days.forEach(day => {
            paddedDays.push({ date: day, currentMonth: true });
        });
        
        return paddedDays;
    };

    const isInRange = (date) => {
        if (tempRange.start && tempRange.end) {
            return isWithinInterval(date, { start: tempRange.start, end: tempRange.end });
        }
        if (tempRange.start && startDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            return isWithinInterval(date, { start, end });
        }
        return false;
    };

    const isStart = (date) => {
        if (tempRange.start && format(date, 'yyyy-MM-dd') === format(tempRange.start, 'yyyy-MM-dd')) {
            return true;
        }
        if (startDate && format(date, 'yyyy-MM-dd') === format(new Date(startDate), 'yyyy-MM-dd')) {
            return true;
        }
        return false;
    };

    const isEnd = (date) => {
        if (tempRange.end && format(date, 'yyyy-MM-dd') === format(tempRange.end, 'yyyy-MM-dd')) {
            return true;
        }
        if (endDate && format(date, 'yyyy-MM-dd') === format(new Date(endDate), 'yyyy-MM-dd')) {
            return true;
        }
        return false;
    };

    const navigateMonth = (direction) => {
        const newDate = new Date(viewDate);
        newDate.setMonth(newDate.getMonth() + direction);
        setViewDate(newDate);
    };

    const goToToday = () => {
        setViewDate(new Date());
    };

    return (
        <div className="w-full" ref={dropdownRef}>
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    {label} <span className="text-red-500">*</span>
                </label>
            )}

            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={`w-full px-1 py-1.5 bg-white border rounded-xl text-left transition-all flex items-center justify-between gap-3 ${
                    startDate && endDate
                        ? 'border-blue-300 bg-blue-50 ring-2 ring-blue-100'
                        : 'border-gray-300 hover:border-gray-400'
                }`}
            >
                <div className="flex items-center gap-3">
                    <div className={`w-7 h-7 rounded-lg flex items-center justify-center ${
                        startDate && endDate ? 'bg-blue-500' : 'bg-gray-100'
                    }`}>
                        <svg className={`w-5 h-5 ${startDate && endDate ? 'text-white' : 'text-gray-400'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <div className="text-sm font-medium text-gray-900">{displayValue()}</div>
                        {startDate && endDate && (
                            <div className="text-xs text-blue-600 font-medium">
                                {Math.ceil((new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24)) + 1} days selected
                            </div>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {startDate && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                onChange({ startDate: '', endDate: '' });
                            }}
                            className="p-1 hover:bg-gray-100 rounded-full"
                        >
                            <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    )}
                    <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={isOpen ? "M5 15l7-7 7 7" : "M19 9l-7 7-7-7"} />
                    </svg>
                </div>
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-2 bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
                    <div className="flex">
                        <div className="w-72 border-r border-gray-200 p-4">
                            <div className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                Quick Select
                            </div>
                            <div className="relative mb-3">
                                <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Search dates..."
                                    className="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                />
                                {searchQuery && (
                                    <button
                                        type="button"
                                        onClick={() => setSearchQuery('')}
                                        className="absolute right-2 top-1/2 -translate-y-1/2 p-1 hover:bg-gray-100 rounded-full"
                                    >
                                        <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                )}
                            </div>
                            {searchQuery && (
                                <div className="text-xs text-gray-500 mb-2 px-1">
                                    {filteredPresets.length} result{filteredPresets.length !== 1 ? 's' : ''} found
                                </div>
                            )}
                            <div className="space-y-1 max-h-80 overflow-y-auto">
                                {filteredPresets.map((preset) => (
                                    <button
                                        key={preset.label}
                                        type="button"
                                        onClick={() => handlePresetClick(preset)}
                                        className="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-between group"
                                    >
                                        <span className="text-gray-700">{preset.label}</span>
                                        <svg className="w-4 h-4 text-gray-300 group-hover:text-blue-500 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                ))}
                                {filteredPresets.length === 0 && (
                                    <div className="text-center py-4 text-sm text-gray-500">
                                        No matching presets
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="p-4 w-80">
                            <div className="flex items-center justify-between mb-4">
                                <button
                                    type="button"
                                    onClick={() => navigateMonth(-1)}
                                    className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-semibold text-gray-900">
                                        {format(viewDate, 'MMMM yyyy')}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={goToToday}
                                        className="text-xs px-2 py-1 bg-blue-50 text-blue-600 rounded-md hover:bg-blue-100 transition-colors font-medium"
                                    >
                                        Today
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => navigateMonth(1)}
                                    className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    <svg className="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>

                            <div className="grid grid-cols-7 gap-1 mb-2">
                                {['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'].map((day) => (
                                    <div key={day} className="text-center text-xs font-medium text-gray-500 py-1">
                                        {day}
                                    </div>
                                ))}
                            </div>

                            <div className="grid grid-cols-7 gap-1">
                                {getDaysInMonth().map(({ date, currentMonth }, index) => {
                                    const inRange = isInRange(date);
                                    const isStartDay = isStart(date);
                                    const isEndDay = isEnd(date);
                                    const isToday = format(date, 'yyyy-MM-dd') === format(new Date(), 'yyyy-MM-dd');

                                    return (
                                        <button
                                            key={index}
                                            type="button"
                                            onClick={() => handleDayClick(date)}
                                            className={`
                                                w-9 h-9 text-sm rounded-lg transition-all
                                                ${!currentMonth ? 'text-gray-300' : 'text-gray-700 hover:bg-gray-100'}
                                                ${inRange && !isStartDay && !isEndDay ? 'bg-blue-50 text-blue-600' : ''}
                                                ${isStartDay || isEndDay ? 'bg-blue-500 text-white font-semibold' : ''}
                                                ${isToday && !isStartDay && !isEndDay ? 'ring-2 ring-blue-300 ring-inset' : ''}
                                            `}
                                        >
                                            {format(date, 'd')}
                                        </button>
                                    );
                                })}
                            </div>

                            <div className="mt-4 pt-4 border-t border-gray-100">
                                <div className="text-xs text-gray-500">
                                    {selectingStart ? (
                                        <span className="flex items-center gap-2">
                                            <span className="w-2 h-2 bg-blue-500 rounded-full"></span>
                                            Click to select start date
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-2">
                                            <span className="w-2 h-2 bg-blue-500 rounded-full"></span>
                                            Click to select end date
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
