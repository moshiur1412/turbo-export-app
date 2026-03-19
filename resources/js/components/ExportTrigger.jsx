import { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';

interface ExportTriggerProps {
    model: string;
    columns: string[];
    filters?: Record<string, unknown>;
    format?: 'csv' | 'xlsx';
    filename?: string;
    onStart?: (exportId: string) => void;
    onError?: (error: string) => void;
    buttonText?: string;
    buttonVariant?: 'primary' | 'secondary' | 'danger';
    disabled?: boolean;
}

export default function ExportTrigger({
    model,
    columns,
    filters = {},
    format = 'csv',
    filename,
    onStart,
    onError,
    buttonText = 'Export Data',
    buttonVariant = 'primary',
    disabled = false,
}: ExportTriggerProps) {
    const [isExporting, setIsExporting] = useState(false);
    const [exportId, setExportId] = useState<string | null>(null);

    const handleExport = useCallback(async () => {
        setIsExporting(true);

        try {
            const response = await fetch('/api/exports', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    model,
                    columns,
                    filters,
                    format,
                    filename,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Failed to start export');
            }

            const newExportId = data.export_id;
            setExportId(newExportId);
            onStart?.(newExportId);
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Unknown error';
            onError?.(errorMessage);
        } finally {
            setIsExporting(false);
        }
    }, [model, columns, filters, format, filename, onStart, onError]);

    const baseClasses = 'inline-flex items-center justify-center px-4 py-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

    const variantClasses = {
        primary: 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
        secondary: 'bg-gray-200 hover:bg-gray-300 text-gray-800 focus:ring-gray-500',
        danger: 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
    };

    return (
        <div className="flex flex-col gap-2">
            <button
                type="button"
                onClick={handleExport}
                disabled={disabled || isExporting}
                className={`${baseClasses} ${variantClasses[buttonVariant]}`}
            >
                {isExporting ? (
                    <>
                        <svg
                            className="animate-spin -ml-1 mr-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                className="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                strokeWidth="4"
                            />
                            <path
                                className="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        Starting Export...
                    </>
                ) : (
                    buttonText
                )}
            </button>

            {exportId && (
                <p className="text-xs text-gray-500">
                    Export ID: {exportId}
                </p>
            )}
        </div>
    );
}
