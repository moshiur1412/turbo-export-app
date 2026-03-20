import { useState, useCallback } from 'react';
import ExportTrigger from './ExportTrigger';
import ExportProgress from './ExportProgress';

export default function ExportDashboard({
    model,
    columns,
    filters = {},
    format = 'csv',
    filename,
    buttonText = 'Export Data',
    buttonVariant = 'primary',
    showTriggerAfterComplete = false,
    autoDownload = false,
}) {
    const [exportId, setExportId] = useState(null);
    const [completed, setCompleted] = useState(false);

    const handleStart = useCallback((newExportId) => {
        setExportId(newExportId);
        setCompleted(false);
    }, []);

    const handleComplete = useCallback((downloadUrl) => {
        setCompleted(true);
        
        if (autoDownload) {
            window.open(downloadUrl, '_blank');
        }
    }, [autoDownload]);

    const handleReset = useCallback(() => {
        setExportId(null);
        setCompleted(false);
    }, []);

    if (exportId && !completed) {
        return (
            <div className="space-y-4">
                <ExportProgress
                    exportId={exportId}
                    onComplete={handleComplete}
                    pollingInterval={1000}
                />
                
                <button
                    onClick={handleReset}
                    className="text-sm text-gray-500 hover:text-gray-700 underline"
                >
                    Start New Export
                </button>
            </div>
        );
    }

    if (completed && !showTriggerAfterComplete) {
        return (
            <div className="text-center p-6">
                <p className="text-green-600 font-medium mb-4">
                    Export completed successfully!
                </p>
                <button
                    onClick={handleReset}
                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                >
                    Export Again
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {completed && showTriggerAfterComplete && (
                <div className="p-3 bg-green-50 border border-green-200 rounded-md">
                    <p className="text-sm text-green-600">
                        Previous export completed successfully!
                    </p>
                </div>
            )}
            
            <ExportTrigger
                model={model}
                columns={columns}
                filters={filters}
                format={format}
                filename={filename}
                buttonText={buttonText}
                buttonVariant={buttonVariant}
                onStart={handleStart}
            />
        </div>
    );
}
